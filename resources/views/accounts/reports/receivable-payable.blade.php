@extends('layouts.app')
@section('title', 'Receivables & Payables')
@section('subtitle', 'Who owes the shop, and who the shop owes, on one date')

@section('content')

<x-filters :action="route('accounts.reports.receivable-payable')">
    <div>
        <label class="label">As on</label>
        <input type="date" name="as_on" value="{{ $asOn }}" class="input">
    </div>
    <button class="btn-primary">Apply</button>
    <a href="{{ route('accounts.reports.export', ['report' => 'receivable-payable', 'as_on' => $asOn]) }}"
       class="btn-outline">Export CSV</a>
    <button type="button" onclick="window.print()" class="btn-outline">Print</button>
</x-filters>

<div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
    <x-stat label="Receivable" :value="cur($data['total_receivable'])"
            :sub="count($data['receivable']) . ' parties'" icon="trend" tone="blue" />
    <x-stat label="Payable" :value="cur($data['total_payable'])"
            :sub="count($data['payable']) . ' parties'" icon="trenddown" tone="amber" />
    <x-stat :label="$data['net'] >= 0 ? 'Net Receivable' : 'Net Payable'"
            :value="cur(abs($data['net']))"
            :icon="$data['net'] >= 0 ? 'check' : 'alert'"
            :tone="$data['net'] >= 0 ? 'green' : 'rose'" />
</div>

<div class="grid grid-cols-1 gap-5 lg:grid-cols-2">

    @foreach ([
        ['key' => 'receivable', 'title' => 'Receivable — they owe the shop', 'total' => $data['total_receivable'], 'badge' => 'badge-blue'],
        ['key' => 'payable', 'title' => 'Payable — the shop owes them', 'total' => $data['total_payable'], 'badge' => 'badge-amber'],
    ] as $side)
        <div class="card">
            <div class="card-head">
                <h3 class="card-title">{{ $side['title'] }}</h3>
                <span class="{{ $side['badge'] }}">{{ cur($side['total']) }}</span>
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Party</th>
                            <th>Group</th>
                            <th class="text-right">Amount</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($data[$side['key']] as $r)
                            <tr>
                                <td>
                                    <p class="font-medium">{{ $r['name'] }}</p>
                                    <p class="text-[11.5px] text-ink-500">
                                        {{ $r['accode'] }}@if ($r['phone']) · {{ $r['phone'] }}@endif
                                    </p>
                                </td>
                                <td class="text-ink-600 dark:text-ink-400">{{ $r['group'] ?: '—' }}</td>
                                <td class="num text-right font-semibold">{{ money_in($r['amount']) }}</td>
                                <td class="text-right">
                                    <a href="{{ route('accounts.reports.general-ledger', ['accode' => $r['accode'], 'to' => $asOn]) }}"
                                       class="btn-outline btn-sm">Ledger</a>
                                </td>
                            </tr>
                        @empty
                            <x-empty :cols="4" message="Nothing outstanding on this side" />
                        @endforelse
                    </tbody>
                    @if (count($data[$side['key']]))
                        <tfoot>
                            <tr class="border-t-2 border-ink-300 bg-ink-50 font-bold dark:border-ink-700 dark:bg-ink-950/60">
                                <td colspan="2">Total</td>
                                <td class="num text-right">{{ money_in($side['total']) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    @endforeach
</div>

<p class="mt-3 text-xs text-ink-500">
    A customer, agent or supplier appears on whichever side their balance actually falls, so an
    overpaid customer shows up as payable rather than as a negative receivable.
</p>

@endsection
