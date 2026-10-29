<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentPayout;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgentController extends Controller
{
    public function __construct(protected AccountingService $accounts) {}

    public function index(Request $request)
    {
        $agents = Agent::withCount('customers')
            ->withSum(['payments as collected' => fn ($q) => $q->where('status', 'APPROVED')], 'total')
            ->when($request->q, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%$v%")
                ->orWhere('agent_code', 'like', "%$v%")
                ->orWhere('phone', 'like', "%$v%")))
            ->orderBy('name')
            ->paginate(20)->withQueryString();

        return view('admin.agents.index', compact('agents'));
    }

    public function create()
    {
        return view('admin.agents.form', ['agent' => new Agent(['join_date' => now()->toDateString()])]);
    }

    public function edit(Agent $agent)
    {
        return view('admin.agents.form', compact('agent'));
    }

    public function store(Request $request, ?Agent $agent = null)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => [
                'required', 'string', 'max:20',
                Rule::unique('agents', 'phone')->ignore($agent?->id),
            ],
            'email' => ['nullable', 'email', 'max:120'],
            'aadhaar' => ['nullable', 'string', 'max:20'],
            'pan' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'account_no' => ['nullable', 'string', 'max:40'],
            'ifsc' => ['nullable', 'string', 'max:20'],
            'join_date' => ['nullable', 'date'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('agents', 'public');
        }
        $data['status'] = $request->boolean('status', true);

        if ($agent?->exists) {
            if (blank($data['password'] ?? null)) {
                unset($data['password']);
            } else {
                $data['must_change_password'] = $request->boolean('must_change_password');
            }
            $agent->update($data);
        } else {
            $data['agent_code'] = Agent::nextCode();
            if (blank($data['password'] ?? null)) {
                $data['password'] = $data['phone'];
                $data['must_change_password'] = true;
            } else {
                $data['must_change_password'] = $request->boolean('must_change_password');
            }
            $agent = Agent::create($data);
        }

        $this->accounts->ledgerForAgent($agent);

        return redirect()->route('admin.agents.index')->with('success', 'Agent saved.');
    }

    public function show(Agent $agent)
    {
        $agent->load('payouts');
        $customers = $agent->customers()->latest('id')->limit(25)->get();
        $payments = $agent->payments()->with('customer:id,name')->latest('payment_date')->limit(25)->get();

        return view('admin.agents.show', compact('agent', 'customers', 'payments'));
    }

    public function destroy(Agent $agent)
    {
        if ($agent->customers()->exists()) {
            return back()->withErrors(['error' => 'Agent has customers — deactivate instead.']);
        }
        $agent->delete();

        return back()->with('success', 'Agent deleted.');
    }

    /* ---------------- COMMISSION / PAYOUT ---------------- */

    public function payouts(Request $request)
    {
        $payouts = AgentPayout::with('agent:id,name,agent_code')
            ->when($request->agent_id, fn ($q, $v) => $q->where('agent_id', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->latest('id')->paginate(25)->withQueryString();

        $agents = Agent::active()->orderBy('name')->get(['id', 'name']);

        return view('admin.agents.payouts', compact('payouts', 'agents'));
    }

    /** Pending commission per agent */
    public function pendingCommission()
    {
        $rows = Agent::active()->get()->map(function ($a) {
            $collected = $a->collection();
            $earned = round($collected * $a->commission_percent / 100, 2);
            $paid = (float) $a->payouts()->where('status', 'PAID')->sum('amount');

            return [
                'agent' => $a,
                'collected' => $collected,
                'earned' => $earned,
                'paid' => $paid,
                'pending' => round($earned - $paid, 2),
            ];
        })->filter(fn ($r) => $r['pending'] > 0.005)->sortByDesc('pending')->values();

        return view('admin.agents.pending-commission', compact('rows'));
    }

    public function storePayout(Request $request)
    {
        $data = $request->validate([
            'agent_id' => ['required', 'exists:agents,id'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'amount' => ['required', 'numeric', 'min:1'],
            'received_date' => ['nullable', 'date'],
            'payment_mode' => ['required', Rule::in(['CASH', 'UPI', 'BANK', 'CHEQUE'])],
            'notes' => ['nullable', 'string'],
        ]);

        $agent = Agent::findOrFail($data['agent_id']);
        $data['collected_amount'] = $agent->collection($data['from_date'], $data['to_date']);
        $data['status'] = 'PAID';
        $data['received_date'] ??= now()->toDateString();

        $voucher = $this->accounts->postAgentCommission(
            (float) $data['amount'],
            $data['payment_mode'],
            $data['received_date'],
            ['narration' => 'Commission paid to '.$agent->name.' ('.$agent->agent_code.')']
        );

        $data['daybook_sno'] = $voucher->sno;
        AgentPayout::create($data);

        return back()->with('success', 'Commission paid and posted to accounts.');
    }
}
