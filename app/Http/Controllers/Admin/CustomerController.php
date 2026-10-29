<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Country;
use App\Models\Customer;
use App\Models\CustomerScheme;
use App\Models\District;
use App\Models\Scheme;
use App\Models\State;
use App\Services\AccountingService;
use App\Services\FinancialReportService;
use App\Services\SchemeService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function __construct(
        protected AccountingService $accounts,
        protected SchemeService $schemes,
        protected FinancialReportService $reports
    ) {}

    public function index(Request $request)
    {
        $customers = Customer::with('agent:id,name', 'district:id,name')
            ->withCount(['schemes as active_schemes' => fn ($q) => $q->where('status', 'ACTIVE')])
            ->search($request->q)
            ->when($request->agent_id, fn ($q, $v) => $q->where('agent_id', $v))
            ->when($request->status !== null && $request->status !== '', fn ($q) => $q->where('status', (bool) $request->status))
            ->latest('id')
            ->paginate(20)->withQueryString();

        $agents = Agent::active()->orderBy('name')->get(['id', 'name']);

        return view('admin.customers.index', compact('customers', 'agents'));
    }

    public function create()
    {
        return view('admin.customers.form', [
            'customer' => new Customer(['join_date' => now()->toDateString()]),
        ] + $this->formData());
    }

    public function edit(Customer $customer)
    {
        return view('admin.customers.form', compact('customer') + $this->formData());
    }

    protected function formData(): array
    {
        return [
            'agents' => Agent::active()->orderBy('name')->get(['id', 'name']),
            // For enrolling straight from the create form - a new customer is
            // almost always taking a scheme, and making that a second trip
            // through a second screen is how book numbers got skipped.
            'schemes' => Scheme::where('status', true)->orderBy('name')->get(),
            'nextBookNo' => CustomerScheme::nextBookNo(),
            'countries' => Country::active()->orderBy('name')->get(),
            'states' => State::active()->orderBy('name')->get(),
            'districts' => District::active()->orderBy('name')->get(),
        ];
    }

    public function store(Request $request, ?Customer $customer = null)
    {
        $id = $customer?->id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:20', Rule::unique('customers')->ignore($id)],
            'email' => ['nullable', 'email', 'max:120'],
            'dob' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in(['MALE', 'FEMALE', 'OTHER'])],
            'address' => ['nullable', 'string'],
            'pincode' => ['nullable', 'string', 'max:10'],
            'country_id' => ['nullable', 'exists:countries,id'],
            'state_id' => ['nullable', 'exists:states,id'],
            'district_id' => ['nullable', 'exists:districts,id'],
            'agent_id' => ['nullable', 'exists:agents,id'],
            'aadhaar' => ['nullable', 'string', 'max:20'],
            'pan' => ['nullable', 'string', 'max:20'],
            'kyc_status' => ['nullable', Rule::in(['PENDING', 'VERIFIED', 'REJECTED'])],
            'nominee_name' => ['nullable', 'string', 'max:150'],
            'nominee_relation' => ['nullable', 'string', 'max:60'],
            'nominee_phone' => ['nullable', 'string', 'max:20'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'account_no' => ['nullable', 'string', 'max:40'],
            'ifsc' => ['nullable', 'string', 'max:20'],
            'join_date' => ['nullable', 'date'],
            'password' => ['nullable', 'min:6'],

            // Optional first enrolment, only offered when creating.
            'scheme_id' => ['nullable', 'exists:schemes,id'],
            'book_no' => ['nullable', 'string', 'max:30'],
            'installment_amount' => ['nullable', 'numeric', 'min:1'],
            'scheme_start_date' => ['nullable', 'date'],
        ]);

        $enrolment = [
            'scheme_id' => $data['scheme_id'] ?? null,
            'book_no' => $data['book_no'] ?? null,
            'installment_amount' => $data['installment_amount'] ?? null,
            'start_date' => $data['scheme_start_date'] ?? null,
        ];
        unset($data['scheme_id'], $data['book_no'], $data['installment_amount'], $data['scheme_start_date']);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('customers', 'public');
        }
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $data['status'] = $request->boolean('status', true);

        $isNew = ! $customer?->exists;

        if ($customer?->exists) {
            $customer->update($data);
        } else {
            $data['reg_no'] = Customer::nextRegNo();
            $data['referral_code'] = 'REF'.strtoupper(substr(md5($data['phone'].microtime()), 0, 6));
            $data['password'] ??= $data['phone'];
            $customer = Customer::create($data);
        }

        // Every customer gets a ledger in accountm
        $this->accounts->ledgerForCustomer($customer);

        // Enrol only on create, and only if a scheme was picked. Editing a
        // customer must never quietly hand them a second scheme.
        $message = 'Customer saved.';
        if ($isNew && filled($enrolment['scheme_id'])) {
            try {
                $cs = $this->schemes->enroll(
                    $customer,
                    Scheme::findOrFail($enrolment['scheme_id']),
                    [
                        'book_no' => $enrolment['book_no'],
                        'installment_amount' => $enrolment['installment_amount'],
                        'start_date' => $enrolment['start_date'] ?: now()->toDateString(),
                        'agent_id' => $customer->agent_id,
                    ],
                );
                $message = "Customer created and enrolled - book no {$cs->book_no}, account {$cs->account_no}.";
            } catch (\Throwable $e) {
                // The customer is already saved, so say what is missing rather
                // than losing everything that was typed.
                return redirect()->route('admin.customers.show', $customer)
                    ->withErrors(['enroll' => 'Customer saved, but the scheme was not started: '.$e->getMessage()]);
            }
        }

        return redirect()->route('admin.customers.show', $customer)
            ->with('success', $message);
    }

    public function show(Customer $customer)
    {
        $customer->load([
            'agent', 'district', 'state', 'point',
            'schemes.scheme', 'schemes.installments',
        ]);

        $payments = $customer->payments()->latest('payment_date')->limit(25)->get();

        $ledger = null;
        if ($customer->accode) {
            $ledger = $this->reports->generalLedger(
                $customer->accode,
                now()->subYear()->toDateString(),
                now()->toDateString()
            );
        }

        $schemes = Scheme::active()->get();
        $privilege = app(\App\Services\PrivilegeService::class)->summary($customer);

        return view('admin.customers.show', compact('customer', 'payments', 'ledger', 'schemes', 'privilege'));
    }

    public function destroy(Customer $customer)
    {
        if ($customer->payments()->exists()) {
            return back()->withErrors(['error' => 'Customer has payments — deactivate instead of deleting.']);
        }

        $customer->delete();

        return redirect()->route('admin.customers.index')->with('success', 'Customer deleted.');
    }

    /** Enroll the customer into a scheme */
    public function enroll(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'scheme_id' => ['required', 'exists:schemes,id'],
            'installment_amount' => ['nullable', 'numeric', 'min:1'],
            'start_date' => ['required', 'date'],
            'agent_id' => ['nullable', 'exists:agents,id'],
            'book_no' => ['nullable', 'string', 'max:30'],
        ]);

        $this->schemes->enroll($customer, Scheme::findOrFail($data['scheme_id']), $data);

        return back()->with('success', 'Scheme enrolled and installment schedule created.');
    }

    /* AJAX cascades */
    public function states(int $country)
    {
        return State::active()->where('country_id', $country)->orderBy('name')->get(['id', 'name']);
    }

    public function districts(int $state)
    {
        return District::active()->where('state_id', $state)->orderBy('name')->get(['id', 'name']);
    }
}
