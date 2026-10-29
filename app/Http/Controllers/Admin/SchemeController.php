<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerScheme;
use App\Models\Metal;
use App\Models\Scheme;
use App\Services\SchemeService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SchemeController extends Controller
{
    public function __construct(protected SchemeService $service) {}

    public function index()
    {
        $schemes = Scheme::with('metal')->withCount('enrollments')->orderBy('name')->paginate(20);

        return view('admin.schemes.index', compact('schemes'));
    }

    public function create()
    {
        return view('admin.schemes.form', [
            'scheme' => new Scheme(['total_installments' => 11, 'gst_percent' => config('scheme.gst_percent')]),
            'metals' => Metal::active()->get(),
        ]);
    }

    public function edit(Scheme $scheme)
    {
        return view('admin.schemes.form', ['scheme' => $scheme, 'metals' => Metal::active()->get()]);
    }

    public function store(Request $request, ?Scheme $scheme = null)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:30', Rule::unique('schemes', 'code')->ignore($scheme?->id)],
            'metal_id' => ['nullable', 'exists:metals,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_interval' => ['required', Rule::in(config('scheme.payment_intervals'))],
            'total_installments' => ['required', 'integer', 'min:1'],
            'duration_months' => ['nullable', 'integer', 'min:1'],
            'bonus_type' => ['required', Rule::in(['NONE', 'PERCENT', 'AMOUNT', 'INSTALLMENT'])],
            'bonus_value' => ['nullable', 'numeric', 'min:0'],
            'gst_percent' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('schemes', 'public');
        }
        $data['flexible_amount'] = $request->boolean('flexible_amount');
        $data['gram_based'] = $request->boolean('gram_based', true);
        $data['status'] = $request->boolean('status', true);

        $scheme?->exists ? $scheme->update($data) : Scheme::create($data);

        return redirect()->route('admin.schemes.index')->with('success', 'Scheme saved.');
    }

    public function destroy(Scheme $scheme)
    {
        if ($scheme->enrollments()->exists()) {
            return back()->withErrors(['error' => 'Scheme has enrollments — deactivate instead.']);
        }
        $scheme->delete();

        return back()->with('success', 'Scheme deleted.');
    }

    /* ---------------- ENROLLMENTS ---------------- */

    public function enrollments(Request $request)
    {
        $rows = CustomerScheme::with(['customer:id,name,reg_no,phone', 'scheme:id,name', 'agent:id,name'])
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->scheme_id, fn ($q, $v) => $q->where('scheme_id', $v))
            ->when($request->q, fn ($q, $v) => $q->where('account_no', 'like', "%$v%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$v%")
                    ->orWhere('phone', 'like', "%$v%")
                    ->orWhere('reg_no', 'like', "%$v%")))
            ->latest('id')->paginate(25)->withQueryString();

        $schemes = Scheme::orderBy('name')->get(['id', 'name']);

        return view('admin.schemes.enrollments', compact('rows', 'schemes'));
    }

    public function showEnrollment(CustomerScheme $enrollment)
    {
        $enrollment->load(['customer', 'scheme', 'agent', 'installments', 'payments']);

        return view('admin.schemes.enrollment-show', compact('enrollment'));
    }

    /** Matured schemes list */
    public function matured(Request $request)
    {
        $rows = CustomerScheme::with(['customer:id,name,reg_no,phone', 'scheme:id,name'])
            ->where(function ($q) {
                $q->where('status', 'MATURED')
                    ->orWhereColumn('paid_installments', '>=', 'total_installments')
                    ->orWhereDate('maturity_date', '<=', now());
            })
            ->whereNotIn('status', ['CLOSED', 'CANCELLED'])
            ->latest('maturity_date')->paginate(25);

        return view('admin.schemes.matured', compact('rows'));
    }

    public function redeem(Request $request, CustomerScheme $enrollment)
    {
        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0'],
            'date' => ['nullable', 'date'],
            'closure_type' => ['nullable', Rule::in(['GOLD_REDEEM', 'REFUND', 'NO_PAYOUT'])],
            'payment_mode' => ['nullable', Rule::in(['CASH', 'UPI', 'CARD', 'BANK', 'CHEQUE', 'WALLET'])],
            'note' => ['nullable', 'string', 'max:500'],
            'create_new' => ['nullable', 'boolean'],
            'new_start_date' => ['nullable', 'date'],
        ]);

        $result = $this->service->redeem($enrollment, $data + [
            'create_new' => $request->boolean('create_new'),
        ]);

        if (! empty($result['new_scheme'])) {
            return redirect()
                ->route('admin.schemes.enrollment', $result['new_scheme'])
                ->with('success', 'Scheme closed and new enrollment created.');
        }

        return back()->with('success', 'Scheme closed and balance settled.');
    }

    public function block(Request $request, CustomerScheme $enrollment)
    {
        $data = $request->validate([
            'date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->service->block($enrollment, $data);

        return back()->with('success', 'Scheme blocked/cancelled.');
    }

    public function notifyMaturity(Request $request, CustomerScheme $enrollment)
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->service->markMaturityNotified($enrollment, $data);

        return back()->with('success', 'Maturity notification marked for '.$enrollment->account_no.'.');
    }

    /** Expiring / overdue installments */
    public function expiring(Request $request)
    {
        $days = (int) ($request->days ?: 7);
        $rows = $this->service->expiringSoon($days);

        return view('admin.schemes.expiring', compact('rows', 'days'));
    }
}
