<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Bank;
use App\Models\Customer;
use App\Models\CustomerScheme;
use App\Models\Payment;
use App\Services\SchemeService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(protected SchemeService $schemes) {}

    public function index(Request $request)
    {
        $from = $request->from ?: now()->startOfMonth()->toDateString();
        $to = $request->to ?: now()->toDateString();

        $payments = Payment::with(['customer:id,name,reg_no,phone', 'customerScheme:id,account_no', 'agent:id,name'])
            ->whereBetween('payment_date', [$from, $to])
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->payment_mode, fn ($q, $v) => $q->where('payment_mode', $v))
            ->when($request->agent_id, fn ($q, $v) => $q->where('agent_id', $v))
            ->when($request->q, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('receipt_no', 'like', "%$v%")
                ->orWhere('txn_ref', 'like', "%$v%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$v%")
                    ->orWhere('phone', 'like', "%$v%")
                    ->orWhere('reg_no', 'like', "%$v%"))))
            ->orderByDesc('payment_date')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        $totals = [
            'count' => $payments->total(),
            'approved' => (float) Payment::approved()->whereBetween('payment_date', [$from, $to])->sum('total'),
            'pending' => (float) Payment::pending()->whereBetween('payment_date', [$from, $to])->sum('total'),
        ];

        $agents = Agent::active()->orderBy('name')->get(['id', 'name']);

        return view('admin.payments.index', compact('payments', 'from', 'to', 'totals', 'agents'));
    }

    public function pending()
    {
        $payments = Payment::with(['customer:id,name,reg_no,phone', 'customerScheme:id,account_no'])
            ->pending()->orderByDesc('id')->paginate(25);

        return view('admin.payments.pending', compact('payments'));
    }

    public function create(Request $request)
    {
        $customer = $request->customer_id ? Customer::find($request->customer_id) : null;

        return view('admin.payments.form', [
            'customer' => $customer,
            'schemes' => $customer
                ? $customer->schemes()->with('scheme')->where('status', 'ACTIVE')->get()
                : collect(),
            'banks' => Bank::active()->get(),
            'agents' => Agent::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'customer_scheme_id' => ['nullable', 'exists:customer_schemes,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'payment_date' => ['required', 'date'],
            'payment_type' => ['required', Rule::in(['INSTALLMENT', 'DIRECT', 'ADVANCE', 'REDEEM', 'BONUS'])],
            'payment_mode' => ['required', Rule::in(config('scheme.payment_modes'))],
            'txn_ref' => ['nullable', 'string', 'max:80'],
            'bank_id' => ['nullable', 'exists:banks,id'],
            'agent_id' => ['nullable', 'exists:agents,id'],
            'remarks' => ['nullable', 'string'],
        ]);

        if ($request->hasFile('screenshot')) {
            $data['screenshot'] = $request->file('screenshot')->store('payments', 'public');
        }

        $data['status'] = $request->boolean('auto_approve', true) ? 'APPROVED' : 'PENDING';

        $payment = $this->schemes->recordPayment($data);

        return redirect()->route('admin.payments.show', $payment)
            ->with('success', 'Payment '.$payment->receipt_no.' recorded.');
    }

    public function show(Payment $payment)
    {
        $payment->load(['customer', 'customerScheme.scheme', 'installment', 'agent', 'voucher.lines.account']);

        return view('admin.payments.show', compact('payment'));
    }

    public function approve(Payment $payment)
    {
        $this->schemes->approvePayment($payment, auth()->id());

        return back()->with('success', 'Payment approved and posted to accounts.');
    }

    public function reject(Request $request, Payment $payment)
    {
        $this->schemes->rejectPayment($payment, $request->input('reason'));

        return back()->with('success', 'Payment rejected.');
    }

    public function bulkApprove(Request $request)
    {
        $ids = $request->input('ids', []);
        $n = 0;

        foreach (Payment::pending()->whereIn('id', $ids)->get() as $p) {
            $this->schemes->approvePayment($p, auth()->id());
            $n++;
        }

        return back()->with('success', $n.' payment(s) approved.');
    }

    /** AJAX: schemes of a customer */
    public function customerSchemes(Customer $customer)
    {
        return CustomerScheme::with('scheme:id,name')
            ->where('customer_id', $customer->id)
            ->where('status', 'ACTIVE')
            ->get()
            ->map(fn ($cs) => [
                'id' => $cs->id,
                'label' => $cs->account_no.' — '.$cs->scheme->name,
                'amount' => (float) $cs->installment_amount,
                'paid' => $cs->paid_installments,
                'total' => $cs->total_installments,
            ]);
    }
}
