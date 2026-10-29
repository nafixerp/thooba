@extends('layouts.app')
@section('title', 'Schedules to the Balance Sheet')
@section('subtitle', 'The ledger detail behind each one line figure on the balance sheet')

@section('content')

<x-filters :action="route('accounts.reports.balance-sheet-schedules')">
    <div>
        <label class="label">As on</label>
        <input type="date" name="as_on" value="{{ $asOn }}" class="input">
    </div>
    <button class="btn-primary">Apply</button>
    <a href="{{ route('accounts.reports.balance-sheet', ['as_on' => $asOn]) }}" class="btn-outline">Balance Sheet</a>
    <button type="button" onclick="window.print()" class="btn-outline">Print</button>
</x-filters>

<div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
    <x-stat label="Schedules" :value="number_format(count($data['schedules']))" icon="layers" tone="slate" />
    <x-stat label="Debit total" :value="cur($data['total_debit'])" icon="trend" tone="blue" />
    <x-stat label="Credit total" :value="cur($data['total_credit'])" icon="trenddown" tone="amber" />
</div>

@forelse ($data['schedules'] as $i => $s)
    <div class="card mb-4">
        <div class="card-head">
            <div>
                <h3 class="card-title">Schedule {{ $i + 1 }} — {{ $s['name'] }}</h3>
                <p class="text-xs text-ink-500">
                    As on {{ \Carbon\Carbon::parse($asOn)->format('d M Y') }} · {{ count($s['accounts']) }} ledger(s)
                </p>
            </div>
            <span class="num text-[15px] font-bold">
                {{ money_in(abs($s['total'])) }}
                <span class="ml-0.5 text-[11px] font-medium text-ink-500">{{ $s['total'] >= 0 ? 'Dr' : 'Cr' }}</span>
            </span>
        </div>

        @if ($s['expand'])
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Ledger</th>
                            <th class="text-right">Balance</th>
                            <th class="w-16">Side</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($s['accounts'] as $a)
                            <tr>
                                <td class="font-mono text-xs text-ink-500">{{ $a['accode'] }}</td>
                                <td class="font-medium">
                                    <a href="{{ route('accounts.reports.general-ledger', ['accode' => $a['accode'], 'to' => $asOn]) }}"
                                       class="hover:text-brand-600">{{ $a['name'] }}</a>
                                </td>
                                <td class="num text-right">{{ money_in(abs($a['balance'])) }}</td>
                                <td>
                                    <span class="{{ $a['side'] === 'Dr' ? 'badge-blue' : 'badge-amber' }}">{{ $a['side'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="px-5 py-4 text-sm text-ink-500">
                This schedule prints collapsed. Turn “expand” on for its group base to list the ledgers.
            </p>
        @endif
    </div>
@empty
    <div class="card card-pad text-center text-sm text-ink-500">
        No balance sheet ledger carries a balance on this date.
    </div>
@endforelse

@endsection
