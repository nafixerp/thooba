@extends('layouts.app')
@section('title', 'New Payment')
@section('subtitle', 'Approved payments post Dr Cash/Bank · Cr Customer · Cr GST')

@section('content')

<form method="POST" action="{{ route('admin.payments.store') }}" enctype="multipart/form-data"
      x-data="payform({{ $customer?->id ?? 'null' }}, {{ config('scheme.gst_percent') }})">
    @csrf

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="card-head"><h3 class="card-title">Payment Details</h3></div>
            <div class="card-pad grid grid-cols-1 gap-4 sm:grid-cols-2">

                <div class="sm:col-span-2">
                    <label class="label">Customer *</label>
                    @if ($customer)
                        <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                        <div class="flex items-center gap-3 rounded-lg border border-ink-300 bg-ink-50 px-3 py-2 dark:border-ink-700 dark:bg-ink-950">
                            <span class="grid h-8 w-8 place-items-center rounded-full bg-gradient-to-br from-brand-300 to-brand-600 text-[11px] font-bold text-white">
                                {{ strtoupper(substr($customer->name, 0, 2)) }}
                            </span>
                            <div>
                                <div class="text-sm font-medium">{{ $customer->name }}</div>
                                <div class="text-xs text-ink-500">{{ $customer->reg_no }} · {{ $customer->phone }}</div>
                            </div>
                            <a href="{{ route('admin.payments.create') }}" class="ml-auto text-xs text-brand-600 hover:underline">Change</a>
                        </div>
                    @else
                        <select name="customer_id" class="input" required @change="loadSchemes($event.target.value)">
                            <option value="">— select customer —</option>
                            @foreach (\App\Models\Customer::active()->orderBy('name')->get(['id','name','reg_no','phone']) as $c)
                                <option value="{{ $c->id }}">{{ $c->name }} — {{ $c->reg_no }} ({{ $c->phone }})</option>
                            @endforeach
                        </select>
                    @endif
                </div>

                <div class="sm:col-span-2">
                    <label class="label">Scheme Account</label>
                    <select name="customer_scheme_id" class="input" x-model="schemeId" @change="applySchemeAmount()">
                        <option value="">— direct payment (no scheme) —</option>
                        @foreach ($schemes as $cs)
                            <option value="{{ $cs->id }}" data-amount="{{ $cs->installment_amount }}">
                                {{ $cs->account_no }} — {{ $cs->scheme->name }}
                                ({{ $cs->paid_installments }}/{{ $cs->total_installments }} paid)
                            </option>
                        @endforeach
                        <template x-for="s in remoteSchemes" :key="s.id">
                            <option :value="s.id" x-text="s.label"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <label class="label">Amount *</label>
                    <input type="number" step="0.01" name="amount" x-model.number="amount" @input="calc()" class="input font-mono" required>
                </div>
                <div>
                    <label class="label">GST / Tax</label>
                    <input type="number" step="0.01" name="tax" x-model.number="tax" class="input font-mono">
                    <p class="form-hint">Auto-calculated at <span x-text="gst"></span>%</p>
                </div>
                <div>
                    <label class="label">Gold Rate (per gram)</label>
                    <input type="number" step="0.01" name="rate" x-model.number="rate" @input="calc()" class="input font-mono"
                           value="{{ \App\Models\MetalRate::latest_rate() }}">
                </div>
                <div>
                    <label class="label">Gram (calculated)</label>
                    <input class="input font-mono" :value="gram" readonly>
                </div>
                <div>
                    <label class="label">Payment Date *</label>
                    <input type="date" name="payment_date" class="input" required value="{{ now()->toDateString() }}">
                </div>
                <div>
                    <label class="label">Payment Type *</label>
                    <select name="payment_type" class="input" required>
                        @foreach (['INSTALLMENT','DIRECT','ADVANCE','BONUS'] as $t)
                            <option value="{{ $t }}">{{ ucfirst(strtolower($t)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Payment Mode *</label>
                    <select name="payment_mode" class="input" required>
                        @foreach (config('scheme.payment_modes') as $m)
                            <option value="{{ $m }}">{{ $m }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Transaction Ref</label>
                    <input name="txn_ref" class="input" placeholder="UPI / cheque no">
                </div>
                <div>
                    <label class="label">Agent</label>
                    <select name="agent_id" class="input">
                        <option value="">— none —</option>
                        @foreach ($agents as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Bank / Counter</label>
                    <select name="bank_id" class="input">
                        <option value="">— none —</option>
                        @foreach ($banks as $b)<option value="{{ $b->id }}">{{ $b->bank_name }} — {{ $b->account_no }}</option>@endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Payment Proof</label>
                    <input type="file" name="screenshot" accept="image/*" class="input">
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Remarks</label>
                    <textarea name="remarks" rows="2" class="input"></textarea>
                </div>
            </div>
        </div>

        <div class="space-y-5">
            <div class="card">
                <div class="card-head"><h3 class="card-title">Summary</h3></div>
                <div class="card-pad space-y-2.5 text-sm">
                    <div class="flex justify-between"><span class="text-ink-500">Amount</span><span class="font-mono" x-text="fmt(amount)"></span></div>
                    <div class="flex justify-between"><span class="text-ink-500">GST</span><span class="font-mono" x-text="fmt(tax)"></span></div>
                    <div class="flex justify-between border-t border-ink-200 pt-2.5 dark:border-ink-800">
                        <span class="font-semibold">Total</span>
                        <span class="font-mono text-lg font-bold" x-text="fmt(total)"></span>
                    </div>
                    <div class="flex justify-between"><span class="text-ink-500">Gram credited</span><span class="font-mono" x-text="gram + ' g'"></span></div>
                </div>
            </div>

            <div class="card card-pad">
                <label class="flex items-start gap-2.5 text-sm">
                    <input type="checkbox" name="auto_approve" value="1" checked
                           class="mt-0.5 h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500 dark:border-ink-700 dark:bg-ink-900">
                    <span>
                        <span class="font-medium">Approve immediately</span>
                        <span class="mt-0.5 block text-xs text-ink-500">Posts the voucher to the daybook right away. Uncheck to leave it pending.</span>
                    </span>
                </label>
            </div>

            <div class="flex gap-2">
                <button class="btn-primary flex-1">Save Payment</button>
                <a href="{{ route('admin.payments.index') }}" class="btn-outline">Cancel</a>
            </div>
        </div>
    </div>
</form>

@endsection

@push('scripts')
<script>
function payform(customerId, gstPct) {
    return {
        gst: gstPct,
        amount: 0,
        tax: 0,
        rate: {{ \App\Models\MetalRate::latest_rate() ?: 0 }},
        schemeId: '',
        remoteSchemes: [],
        get total() { return (Number(this.amount) || 0) + (Number(this.tax) || 0); },
        get gram() {
            const r = Number(this.rate) || 0;
            return r > 0 ? ((Number(this.amount) || 0) / r).toFixed(3) : '0.000';
        },
        calc() { this.tax = Math.round((Number(this.amount) || 0) * this.gst) / 100; },
        applySchemeAmount() {
            const opt = document.querySelector(`option[value="${this.schemeId}"]`);
            if (opt && opt.dataset.amount) { this.amount = Number(opt.dataset.amount); this.calc(); }
        },
        async loadSchemes(id) {
            if (!id) { this.remoteSchemes = []; return; }
            const r = await fetch(`/admin/ajax/customer-schemes/${id}`, { headers: { Accept: 'application/json' } });
            this.remoteSchemes = await r.json();
        },
        fmt(n) { return '₹ ' + Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
    };
}
</script>
@endpush
