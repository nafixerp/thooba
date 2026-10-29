<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerScheme;
use App\Models\Payment;
use App\Models\Scheme;
use App\Services\AccountingService;
use App\Services\SchemeService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Everything the agent app does after login: see their book, enrol new
 * customers, and collect installments in the field.
 */
class AgentApiController extends Controller
{
    public function __construct(
        protected SchemeService $service,
        protected AccountingService $accounts,
        protected SmsService $sms,
    ) {}

    /** GET /api/v1/agent/dashboard */
    public function dashboard(Request $request)
    {
        $agent = $request->user();
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        $customerIds = $agent->customers()->pluck('id');

        return $this->ok([
            'agent' => [
                'name' => $agent->name,
                'agent_code' => $agent->agent_code,
                'commission_percent' => (float) $agent->commission_percent,
            ],
            'total_customers' => $customerIds->count(),
            'active_customers' => $agent->customers()->where('status', true)->count(),
            'active_schemes' => CustomerScheme::where('agent_id', $agent->id)->where('status', 'ACTIVE')->count(),
            'collection_today' => (float) $agent->payments()
                ->where('status', 'APPROVED')->whereDate('payment_date', $today)->sum('total'),
            'collection_month' => (float) $agent->payments()
                ->where('status', 'APPROVED')->whereBetween('payment_date', [$monthStart, $today])->sum('total'),
            'collection_total' => $agent->collection(),
            'pending_approval' => (float) $agent->payments()->where('status', 'PENDING')->sum('total'),
            'pending_commission' => $agent->pendingCommission(),
            'due_today' => $this->dueQuery($agent)->whereDate('due_date', $today)->count(),
            'overdue' => $this->dueQuery($agent)->whereDate('due_date', '<', $today)->count(),
        ]);
    }

    /** GET /api/v1/agent/customers?search=&page= */
    public function customers(Request $request)
    {
        $rows = $request->user()->customers()
            ->with('schemes:id,customer_id,status')
            ->when($request->search, fn ($q, $v) => $q->search($v))
            ->when($request->filled('status'), fn ($q) => $q->where('status', (bool) $request->status))
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return $this->ok($rows->through(fn ($c) => [
            'id' => $c->id,
            'reg_no' => $c->reg_no,
            'name' => $c->name,
            'phone' => $c->phone,
            'photo' => $c->photo ? storage_url($c->photo) : null,
            'wallet_amount' => (float) $c->wallet_amount,
            'total_gram' => (float) $c->total_gram,
            'active_schemes' => $c->schemes->where('status', 'ACTIVE')->count(),
            'status' => (bool) $c->status,
        ]));
    }

    /** GET /api/v1/agent/customers/{id} */
    public function customerDetail(Request $request, int $id)
    {
        $customer = $this->ownedCustomer($request, $id);

        $schemes = CustomerScheme::with('scheme:id,name,code')
            ->where('customer_id', $customer->id)
            ->latest('id')->get()
            ->map(fn ($cs) => $this->schemeRow($cs));

        return $this->ok([
            'id' => $customer->id,
            'reg_no' => $customer->reg_no,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'address' => $customer->address,
            'photo' => $customer->photo ? storage_url($customer->photo) : null,
            'wallet_amount' => (float) $customer->wallet_amount,
            'total_gram' => (float) $customer->total_gram,
            'kyc_status' => $customer->kyc_status,
            'join_date' => $customer->join_date?->toDateString(),
            'schemes' => $schemes,
            'recent_payments' => Payment::where('customer_id', $customer->id)
                ->orderByDesc('payment_date')->orderByDesc('id')->limit(10)->get()
                ->map(fn ($p) => $this->paymentRow($p)),
        ]);
    }

    /**
     * POST /api/v1/agent/customers
     * Agent enrols a walk-in customer. Password defaults to the phone number.
     */
    public function storeCustomer(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:120'],
            'address' => ['nullable', 'string'],
            'pincode' => ['nullable', 'string', 'max:10'],
            'district_id' => ['nullable', 'exists:districts,id'],
            'nominee_name' => ['nullable', 'string', 'max:150'],
            'nominee_relation' => ['nullable', 'string', 'max:60'],
            'nominee_phone' => ['nullable', 'string', 'max:20'],
            // An agent can start the customer's first scheme in the same
            // visit. Older app builds may still create only the customer, so
            // scheme_id remains optional at the API boundary.
            'scheme_id' => [
                'nullable',
                Rule::exists('schemes', 'id')->where(fn ($q) => $q->where('status', true)),
            ],
            'book_no' => [
                'nullable',
                'required_with:scheme_id',
                'string',
                'max:30',
                Rule::unique('customer_schemes', 'book_no'),
            ],
            'installment_amount' => ['nullable', 'numeric', 'min:1'],
        ]);

        $phone = $this->sms->normalize($data['phone']);

        if (Customer::where('phone', $phone)->exists()) {
            return $this->fail('This number is already registered.', 422);
        }

        $enrolment = [
            'scheme_id' => $data['scheme_id'] ?? null,
            'book_no' => $data['book_no'] ?? null,
            'installment_amount' => $data['installment_amount'] ?? null,
        ];
        unset($data['scheme_id'], $data['book_no'], $data['installment_amount']);

        [$customer, $customerScheme] = DB::transaction(function () use ($data, $enrolment, $phone, $request) {
            $customer = Customer::create($data + [
                'phone' => $phone,
                'reg_no' => Customer::nextRegNo(),
                'agent_id' => $request->user()->id,
                'source' => 'AGENT',
                // Phone is the default password; the app forces a change at login.
                'password' => $phone,
                'must_change_password' => true,
                'referral_code' => 'REF'.strtoupper(substr(md5($phone.microtime()), 0, 6)),
                'join_date' => now()->toDateString(),
                'status' => true,
            ]);

            $this->accounts->ledgerForCustomer($customer);

            $customerScheme = null;
            if (filled($enrolment['scheme_id'])) {
                $customerScheme = $this->service->enroll(
                    $customer,
                    Scheme::findOrFail($enrolment['scheme_id']),
                    [
                        'book_no' => $enrolment['book_no'],
                        'installment_amount' => $enrolment['installment_amount'],
                        'agent_id' => $request->user()->id,
                        'start_date' => now()->toDateString(),
                        'source' => 'AGENT',
                    ],
                );
            }

            return [$customer, $customerScheme];
        });

        $this->sms->send($phone, config('mobile.app_name').': Welcome '.$customer->name
            .'. Your registration number is '.$customer->reg_no
            .'. Login to the app with your mobile number, password '.$phone.'.');

        return $this->ok([
            'id' => $customer->id,
            'reg_no' => $customer->reg_no,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'scheme' => $customerScheme ? [
                'id' => $customerScheme->id,
                'scheme_id' => $customerScheme->scheme_id,
                'account_no' => $customerScheme->account_no,
                'book_no' => $customerScheme->book_no,
            ] : null,
        ], $customerScheme ? 'Customer registered and scheme started' : 'Customer registered');
    }

    /** POST /api/v1/agent/schemes/join — enrol one of the agent's customers */
    public function joinScheme(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'scheme_id' => ['required', 'exists:schemes,id'],
            'installment_amount' => ['nullable', 'numeric', 'min:1'],
            'start_date' => ['nullable', 'date'],
        ]);

        $customer = $this->ownedCustomer($request, (int) $data['customer_id']);

        try {
            $cs = $this->service->enroll(
                $customer,
                Scheme::findOrFail($data['scheme_id']),
                $data + [
                    'agent_id' => $request->user()->id,
                    'start_date' => $data['start_date'] ?? now()->toDateString(),
                    'source' => 'AGENT',
                ]
            );
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->ok([
            'id' => $cs->id,
            'account_no' => $cs->account_no,
            'total_installments' => $cs->total_installments,
            'maturity_date' => $cs->maturity_date?->toDateString(),
        ], 'Scheme joined successfully');
    }

    /**
     * POST /api/v1/agent/collections — field collection.
     * Stays PENDING until the office approves it, same as a customer payment.
     */
    public function collect(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'customer_scheme_id' => ['nullable', 'exists:customer_schemes,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_mode' => ['required', 'in:CASH,UPI,CARD,BANK,CHEQUE'],
            'txn_ref' => ['nullable', 'string', 'max:80'],
            'bank_id' => ['nullable', 'exists:banks,id'],
            'remarks' => ['nullable', 'string'],
        ]);

        $customer = $this->ownedCustomer($request, (int) $data['customer_id']);

        if (! empty($data['customer_scheme_id'])) {
            $owns = CustomerScheme::where('id', $data['customer_scheme_id'])
                ->where('customer_id', $customer->id)->exists();

            if (! $owns) {
                return $this->fail('That scheme does not belong to this customer.', 422);
            }
        }

        if ($request->hasFile('screenshot')) {
            $data['screenshot'] = $request->file('screenshot')->store('payments', 'public');
        }

        $payment = $this->service->recordPayment($data + [
            'agent_id' => $request->user()->id,
            'payment_date' => now()->toDateString(),
            'payment_type' => ! empty($data['customer_scheme_id']) ? 'INSTALLMENT' : 'DIRECT',
            'status' => 'PENDING',
        ]);

        $this->sms->send($customer->phone, config('mobile.app_name').': Received Rs.'
            .number_format((float) $payment->total, 2).' towards your scheme. Receipt '
            .$payment->receipt_no.'. Collected by '.$request->user()->name.'.');

        return $this->ok($this->paymentRow($payment), 'Collection recorded. Waiting for approval.');
    }

    /** GET /api/v1/agent/collections?from=&to=&status= */
    public function collections(Request $request)
    {
        $request->merge([
            'status' => $request->filled('status') ? strtoupper((string) $request->status) : null,
            'mode' => $request->filled('mode') ? strtoupper((string) $request->mode) : null,
        ]);
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', Rule::in(['PENDING', 'APPROVED', 'REJECTED'])],
            'mode' => ['nullable', Rule::in(['CASH', 'UPI', 'CARD', 'BANK', 'CHEQUE'])],
        ]);

        $filtered = fn () => $request->user()->payments()
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['mode'] ?? null, fn ($q, $v) => $q->where('payment_mode', $v))
            ->when(
                isset($filters['from'], $filters['to']),
                fn ($q) => $q
                    ->whereDate('payment_date', '>=', $filters['from'])
                    ->whereDate('payment_date', '<=', $filters['to'])
            );

        $rows = $filtered()
            ->with('customer:id,name,reg_no,phone')
            ->orderByDesc('payment_date')->orderByDesc('id')
            ->paginate($this->perPage($request));

        $payload = $rows->through(fn ($p) => $this->paymentRow($p) + [
            'customer' => $p->customer?->name,
            'reg_no' => $p->customer?->reg_no,
        ])->toArray();

        // The app pages through this list, so it cannot add the rows up itself.
        // These are the totals for the whole filter, not just the page.
        $byMode = $filtered()
            ->selectRaw('payment_mode, SUM(total) as amount')
            ->groupBy('payment_mode')
            ->pluck('amount', 'payment_mode')
            ->map(fn ($amount) => (float) $amount)
            ->all();

        $joined = $request->user()->customers()
            ->when(
                isset($filters['from'], $filters['to']),
                fn ($q) => $q
                    ->whereDate('join_date', '>=', $filters['from'])
                    ->whereDate('join_date', '<=', $filters['to'])
            )
            ->count();

        $dues = $this->dueQuery($request->user())
            ->when(
                isset($filters['from'], $filters['to']),
                fn ($q) => $q
                    ->whereDate('due_date', '>=', $filters['from'])
                    ->whereDate('due_date', '<=', $filters['to'])
            );

        $payload['summary'] = [
            'count' => $rows->total(),
            'amount' => (float) $filtered()->sum('total'),
            'by_mode' => $byMode,
            'joined_count' => $joined,
            'due_count' => (clone $dues)->count(),
            'due_amount' => (float) (clone $dues)
                ->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as balance')
                ->value('balance'),
        ];

        return $this->ok($payload);
    }

    /** GET /api/v1/agent/due-list?days=7 */
    public function dueList(Request $request)
    {
        $filters = $request->validate([
            'days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'overdue_only' => ['nullable', 'boolean'],
        ]);
        $days = (int) ($filters['days'] ?? 7);

        $rows = $this->dueQuery($request->user())
            ->with('customerScheme.customer:id,name,reg_no,phone', 'customerScheme.scheme:id,name')
            ->when(
                $request->boolean('overdue_only'),
                fn ($q) => $q->whereDate('due_date', '<', today()->toDateString()),
                fn ($q) => $q->when(
                    isset($filters['from'], $filters['to']),
                    fn ($dated) => $dated
                        ->whereDate('due_date', '>=', $filters['from'])
                        ->whereDate('due_date', '<=', $filters['to']),
                    fn ($dated) => $dated->whereDate('due_date', '<=', now()->addDays($days)->toDateString()),
                ),
            )
            ->orderBy('due_date')
            ->limit(500)->get()
            ->map(fn ($i) => [
                'installment_id' => $i->id,
                'customer_scheme_id' => $i->customer_scheme_id,
                'account_no' => $i->customerScheme->account_no,
                'customer' => $i->customerScheme->customer->name,
                'phone' => $i->customerScheme->customer->phone,
                'customer_id' => $i->customerScheme->customer_id,
                'scheme' => $i->customerScheme->scheme->name,
                'installment_no' => $i->installment_no,
                'due_date' => $i->due_date->toDateString(),
                'amount' => (float) $i->amount,
                'paid_amount' => (float) $i->paid_amount,
                'balance' => round((float) $i->amount - (float) $i->paid_amount, 2),
                'overdue' => $i->due_date->lt(today()),
            ]);

        return $this->ok($rows);
    }

    /** GET /api/v1/agent/commission */
    public function commission(Request $request)
    {
        $agent = $request->user();

        return $this->ok([
            'commission_percent' => (float) $agent->commission_percent,
            'collected_total' => $agent->collection(),
            'earned' => round($agent->collection() * ((float) $agent->commission_percent / 100), 2),
            'paid' => (float) $agent->payouts()->where('status', 'PAID')->sum('amount'),
            'pending' => $agent->pendingCommission(),
            'payouts' => $agent->payouts()->orderByDesc('id')->limit(24)->get()
                ->map(fn ($p) => [
                    'from' => $p->from_date?->toDateString(),
                    'to' => $p->to_date?->toDateString(),
                    'collected_amount' => (float) $p->collected_amount,
                    'amount' => (float) $p->amount,
                    'received_date' => $p->received_date?->toDateString(),
                    'mode' => $p->payment_mode,
                    'status' => $p->status,
                ]),
        ]);
    }

    /* =====================================================================
     |  helpers
     ===================================================================== */

    /** Pending/overdue installments across the schemes this agent owns. */
    protected function dueQuery($agent)
    {
        return \App\Models\Installment::whereIn('status', ['PENDING', 'PARTIAL', 'OVERDUE'])
            ->whereHas('customerScheme', fn ($q) => $q
                ->where('agent_id', $agent->id)
                ->where('status', 'ACTIVE'));
    }

    /** Load a customer, refusing anyone outside this agent's book. */
    protected function ownedCustomer(Request $request, int $id): Customer
    {
        $customer = Customer::findOrFail($id);

        abort_if($customer->agent_id !== $request->user()->id, 403, 'This customer is not assigned to you.');

        return $customer;
    }

    protected function schemeRow(CustomerScheme $cs): array
    {
        return [
            'id' => $cs->id,
            'account_no' => $cs->account_no,
            'scheme' => $cs->scheme?->name,
            'installment_amount' => (float) $cs->installment_amount,
            'paid_installments' => $cs->paid_installments,
            'total_installments' => $cs->total_installments,
            'progress' => $cs->progress,
            'total_paid' => (float) $cs->total_paid,
            'total_gram' => (float) $cs->total_gram,
            'maturity_date' => $cs->maturity_date?->toDateString(),
            'status' => $cs->status,
        ];
    }

    protected function paymentRow(Payment $p): array
    {
        return [
            'id' => $p->id,
            'receipt_no' => $p->receipt_no,
            'amount' => (float) $p->amount,
            'tax' => (float) $p->tax,
            'total' => (float) $p->total,
            'gram' => (float) $p->gram,
            'date' => $p->payment_date->toDateString(),
            'mode' => $p->payment_mode,
            'status' => $p->status,
            'txn_ref' => $p->txn_ref,
            'screenshot' => $p->screenshot ? storage_url($p->screenshot) : null,
        ];
    }

    protected function ok($data, string $message = 'OK')
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    protected function fail(string $message, int $code = 422)
    {
        return response()->json(['success' => false, 'message' => $message], $code);
    }
}
