@extends('layouts.app')
@section('title', 'Group Base — accountgbs')
@section('subtitle', 'Level 1 of the chart of accounts')

@section('content')

<div class="grid grid-cols-1 gap-5 lg:grid-cols-3">

    <div class="card lg:col-span-2">
        <div class="card-head">
            <h3 class="card-title">Group Bases</h3>
            <span class="badge-gray">{{ $bases->count() }} records</span>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Code</th><th>Name</th><th>Nature</th><th>Head</th>
                        <th>Normal Side</th><th class="text-right">Groups</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($bases as $b)
                        <tr>
                            <td class="font-mono text-xs font-semibold">{{ $b->gbscode }}</td>
                            <td class="font-medium">{{ $b->name }}</td>
                            <td>
                                <span class="{{ in_array($b->nature, ['ASSET','EXPENSE']) ? 'badge-blue' : 'badge-violet badge bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-400' }}">
                                    {{ $b->nature }}
                                </span>
                            </td>
                            <td><span class="badge-gray">{{ $b->bshead }}</span></td>
                            <td class="font-mono text-xs">{{ $b->normal_side === 'D' ? 'Debit' : 'Credit' }}</td>
                            <td class="num">{{ $b->groups_count }}</td>
                            <td><x-status :value="$b->status ? 'ACTIVE' : 'CLOSED'" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h3 class="card-title">Add Group Base</h3></div>
        <form method="POST" action="{{ route('accounts.bases.store') }}" class="card-pad space-y-4">
            @csrf
            <div>
                <label class="label">Code *</label>
                <input name="gbscode" class="input" required maxlength="10" placeholder="5">
            </div>
            <div>
                <label class="label">Name *</label>
                <input name="name" class="input" required placeholder="Suspense">
            </div>
            <div>
                <label class="label">Nature *</label>
                <select name="nature" class="input" required>
                    <option value="ASSET">Asset (BS · Debit)</option>
                    <option value="LIABILITY">Liability (BS · Credit)</option>
                    <option value="INCOME">Income (P&amp;L · Credit)</option>
                    <option value="EXPENSE">Expense (P&amp;L · Debit)</option>
                </select>
                <p class="form-hint">bshead and normal side are set automatically.</p>
            </div>
            <div>
                <label class="label">Sort Order</label>
                <input type="number" name="sortorder" class="input" value="0">
            </div>
            <button class="btn-primary w-full">Save</button>
        </form>
    </div>
</div>

<div class="mt-5 card card-pad">
    <h4 class="mb-3 text-sm font-semibold">Hierarchy</h4>
    <pre class="overflow-x-auto rounded-lg bg-ink-950 p-4 font-mono text-[12px] leading-relaxed text-ink-300">accountgbs  (Assets / Liabilities / Income / Expense)
     |
     v
accountg    (Current Assets, Sundry Debtors, Indirect Expenses ...)
     |
     v
accountm    (Cash, Bank, Customer A, Rent ...)      &lt;-- accode (PK)
     |
     v
daybook     (Ledger entries)  accode FK + opaccode FK
     |
     +--&gt; General Ledger --&gt; Trial Balance --&gt; P&amp;L --&gt; Balance Sheet</pre>
</div>

@endsection
