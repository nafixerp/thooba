@extends('layouts.app')
@section('title', 'New Voucher')
@section('subtitle', 'Multi-line double entry — debit must equal credit before you can save')

@section('content')

<form method="POST" action="{{ route('accounts.vouchers.store') }}"
      x-data="voucher(@js($ledgers), '{{ $type }}')" @submit="if (!balanced) $event.preventDefault()">
    @csrf

    <div class="card mb-5">
        <div class="card-head"><h3 class="card-title">Voucher Details</h3></div>
        <div class="card-pad grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="label">Voucher Type *</label>
                <select name="voucher_type" x-model="type" class="input" required>
                    @foreach ($types as $k => $v)
                        <option value="{{ $k }}">{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Date *</label>
                <input type="date" name="voucher_date" class="input" required value="{{ now()->toDateString() }}">
            </div>
            <div>
                <label class="label">Reference</label>
                <input name="reference" class="input" placeholder="Bill / cheque no">
            </div>
            <div>
                <label class="label">Narration</label>
                <input name="narration" class="input" placeholder="Being ...">
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h3 class="card-title">Entries</h3>
            <button type="button" @click="addLine()" class="btn-outline btn-sm">+ Add line</button>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:44px">#</th>
                        <th>Ledger Account</th>
                        <th style="width:150px">Dr / Cr</th>
                        <th style="width:180px" class="text-right">Amount</th>
                        <th>Remarks</th>
                        <th style="width:52px"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(l, i) in lines" :key="l.key">
                        <tr>
                            <td class="text-xs text-ink-500" x-text="i + 1"></td>
                            <td>
                                <select :name="`lines[${i}][accode]`" x-model="l.accode" class="input" required>
                                    <option value="">— select ledger —</option>
                                    <template x-for="a in ledgers" :key="a.accode">
                                        <option :value="a.accode" x-text="a.accode + ' — ' + a.name"></option>
                                    </template>
                                </select>
                            </td>
                            <td>
                                <select :name="`lines[${i}][drcr]`" x-model="l.drcr" class="input">
                                    <option value="D">Debit</option>
                                    <option value="C">Credit</option>
                                </select>
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" :name="`lines[${i}][amount]`"
                                       x-model.number="l.amount" class="input text-right font-mono" placeholder="0.00">
                            </td>
                            <td>
                                <input :name="`lines[${i}][remarks]`" x-model="l.remarks" class="input" placeholder="optional">
                            </td>
                            <td class="text-center">
                                <button type="button" @click="removeLine(i)" x-show="lines.length > 2"
                                        class="btn-ghost btn-icon text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10">
                                    &times;
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>

                <tfoot>
                    <tr>
                        <td colspan="3" class="text-right">TOTAL</td>
                        <td class="num"><span class="mr-1 text-[11px] font-semibold text-ink-500">Dr</span><span x-text="fmt(totalDr)"></span></td>
                        <td class="num"><span class="mr-1 text-[11px] font-semibold text-ink-500">Cr</span><span x-text="fmt(totalCr)"></span></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="flex flex-wrap items-center gap-3 border-t border-ink-200 px-5 py-4 dark:border-ink-800">
            <div class="flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-semibold"
                 :class="balanced
                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400'
                    : 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-400'">
                <span x-text="balanced ? '✓ Balanced' : '✗ Difference: ' + fmt(Math.abs(totalDr - totalCr))"></span>
            </div>
            <div class="text-sm text-ink-500">
                Debit <span class="font-mono font-semibold" x-text="fmt(totalDr)"></span>
                &nbsp;·&nbsp;
                Credit <span class="font-mono font-semibold" x-text="fmt(totalCr)"></span>
            </div>
            <div class="ml-auto flex gap-2">
                <a href="{{ route('accounts.vouchers.index') }}" class="btn-outline">Cancel</a>
                <button class="btn-primary" :disabled="!balanced || totalDr === 0">Post Voucher</button>
            </div>
        </div>
    </div>
</form>

@endsection

@push('scripts')
<script>
function voucher(ledgers, type) {
    return {
        ledgers,
        type,
        lines: [
            { key: 1, accode: '', drcr: 'D', amount: null, remarks: '' },
            { key: 2, accode: '', drcr: 'C', amount: null, remarks: '' },
        ],
        seq: 2,
        addLine() {
            this.seq++;
            this.lines.push({ key: this.seq, accode: '', drcr: 'D', amount: null, remarks: '' });
        },
        removeLine(i) { this.lines.splice(i, 1); },
        get totalDr() {
            return this.lines.filter(l => l.drcr === 'D').reduce((s, l) => s + (Number(l.amount) || 0), 0);
        },
        get totalCr() {
            return this.lines.filter(l => l.drcr === 'C').reduce((s, l) => s + (Number(l.amount) || 0), 0);
        },
        get balanced() {
            return Math.abs(this.totalDr - this.totalCr) < 0.005 && this.totalDr > 0;
        },
        fmt(n) {
            return Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
    };
}
</script>
@endpush
