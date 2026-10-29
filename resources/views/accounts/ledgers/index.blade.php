@extends('layouts.app')
@section('title', 'Ledgers — accountm')
@section('subtitle', 'Level 3: the chart of accounts leaf. accode is the PK used by daybook.')

@section('content')

<x-filters :action="route('accounts.ledgers')">
    <div class="min-w-[200px] flex-1">
        <label class="label">Search</label>
        <input name="q" value="{{ request('q') }}" class="input" placeholder="Name, code or phone">
    </div>
    <div class="min-w-[190px]">
        <label class="label">Group</label>
        <select name="grcode" class="input">
            <option value="">All groups</option>
            @foreach ($groups as $g)
                <option value="{{ $g->grcode }}" @selected(request('grcode') === $g->grcode)>{{ $g->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="min-w-[150px]">
        <label class="label">Type</label>
        <select name="actype" class="input">
            <option value="">All types</option>
            @foreach (['CASH','BANK','CUSTOMER','AGENT','SUPPLIER','INCOME','EXPENSE','CAPITAL','DUTY','STOCK','GENERAL'] as $t)
                <option value="{{ $t }}" @selected(request('actype') === $t)>{{ $t }}</option>
            @endforeach
        </select>
    </div>
    <div class="min-w-[130px]">
        <label class="label">Head</label>
        <select name="bshead" class="input">
            <option value="">All</option>
            <option value="BS" @selected(request('bshead') === 'BS')>Balance Sheet</option>
            <option value="PL" @selected(request('bshead') === 'PL')>Profit &amp; Loss</option>
        </select>
    </div>
    <button class="btn-primary">Filter</button>
    <a href="{{ route('accounts.ledgers.create') }}" class="btn-dark">+ New Ledger</a>
</x-filters>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Code</th><th>Ledger Name</th><th>Group</th><th>Type</th><th>Head</th>
                    <th class="text-right">Opening</th><th class="text-right">Closing</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($ledgers as $l)
                    <tr>
                        <td class="font-mono text-xs font-semibold">{{ $l->accode }}</td>
                        <td>
                            <a href="{{ route('accounts.reports.general-ledger', ['accode' => $l->accode]) }}"
                               class="font-medium hover:text-brand-600 hover:underline">{{ $l->name }}</a>
                            @if ($l->is_system)<span class="ml-1 badge-gray">system</span>@endif
                            @if ($l->phone)<div class="text-xs text-ink-500">{{ $l->phone }}</div>@endif
                        </td>
                        <td class="text-ink-600 dark:text-ink-400">{{ $l->group?->name }}</td>
                        <td><span class="badge-gray">{{ $l->actype }}</span></td>
                        <td><span class="{{ $l->bshead === 'BS' ? 'badge-blue' : 'badge-amber' }}">{{ $l->bshead }}</span></td>
                        <td class="num text-ink-500">
                            {{ $l->opening_balance > 0 ? money_in($l->opening_balance) . ' ' . $l->opening_drcr . 'r' : '—' }}
                        </td>
                        <td class="num font-semibold {{ $l->closing < 0 ? 'text-amber-600 dark:text-amber-400' : '' }}">
                            {{ drcr_label($l->closing) }}
                        </td>
                        <td class="whitespace-nowrap text-right">
                            <a href="{{ route('accounts.ledgers.edit', $l->accode) }}" class="btn-ghost btn-sm">Edit</a>
                            <a href="{{ route('accounts.reports.general-ledger', ['accode' => $l->accode]) }}" class="btn-outline btn-sm">Ledger</a>
                        </td>
                    </tr>
                @empty
                    <x-empty cols="8" message="No ledgers found" />
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($ledgers->hasPages())
        <div class="border-t border-ink-200 px-5 py-3 dark:border-ink-800">{{ $ledgers->links() }}</div>
    @endif
</div>

@endsection
