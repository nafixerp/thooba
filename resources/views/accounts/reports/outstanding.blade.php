@extends('layouts.app')
@section('title', 'Outstanding Balances')
@section('subtitle', 'Party-wise balances straight from the ledger')

@section('content')

<x-filters :action="route('accounts.reports.outstanding')">
    <div class="min-w-[180px]">
        <label class="label">Party Type</label>
        <select name="type" class="input" onchange="this.form.submit()">
            @foreach (['CUSTOMER' => 'Customers', 'AGENT' => 'Agents', 'SUPPLIER' => 'Suppliers'] as $k => $v)
                <option value="{{ $k }}" @selected($actype === $k)>{{ $v }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="label">As on</label>
        <input type="date" name="as_on" value="{{ $asOn }}" class="input">
    </div>
    <button class="btn-primary">Apply</button>
    <button type="button" onclick="window.print()" class="btn-outline">Print</button>
</x-filters>

@php
    $dr = collect($rows)->where('balance', '>', 0)->sum('balance');
    $cr = abs(collect($rows)->where('balance', '<', 0)->sum('balance'));
@endphp

<div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
    <x-stat label="Parties" :value="number_format(count($rows))" icon="users" tone="slate" />
    <x-stat label="Receivable (Dr)" :value="cur($dr)" icon="trend" tone="blue" />
    <x-stat label="Payable (Cr)" :value="cur($cr)" icon="trenddown" tone="amber" />
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Party</th>
                    <th>Phone</th>
                    <th class="text-right">Balance</th>
                    <th>Side</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td class="font-mono text-xs text-ink-500">{{ $r['accode'] }}</td>
                        <td class="font-medium">{{ $r['name'] }}</td>
                        <td class="text-ink-600 dark:text-ink-400">{{ $r['phone'] ?: '—' }}</td>
                        <td class="num font-semibold">{{ money_in(abs($r['balance'])) }}</td>
                        <td>
                            <span class="{{ $r['side'] === 'Dr' ? 'badge-blue' : 'badge-amber' }}">{{ $r['side'] }}</span>
                        </td>
                        <td class="text-right">
                            <a href="{{ route('accounts.reports.general-ledger', ['accode' => $r['accode'], 'to' => $asOn]) }}"
                               class="btn-outline btn-sm">Ledger</a>
                        </td>
                    </tr>
                @empty
                    <x-empty cols="6" message="No outstanding balances" />
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
