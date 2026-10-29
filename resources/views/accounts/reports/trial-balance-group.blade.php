@extends('layouts.app')
@section('title', 'Trial Balance (Group-wise)')
@section('subtitle', 'The same figures as the ledger trial balance, one line per account group')

@section('content')

<x-filters :action="route('accounts.reports.trial-balance-group')">
    <div>
        <label class="label">From</label>
        <input type="date" name="from" value="{{ $from }}" class="input">
    </div>
    <div>
        <label class="label">To</label>
        <input type="date" name="to" value="{{ $to }}" class="input">
    </div>
    <button class="btn-primary">Apply</button>
    <a href="{{ route('accounts.reports.export', ['report' => 'trial-balance-group', 'from' => $from, 'to' => $to]) }}"
       class="btn-outline">Export CSV</a>
    <a href="{{ route('accounts.reports.trial-balance', ['from' => $from, 'to' => $to]) }}" class="btn-outline">Ledger-wise</a>
    <button type="button" onclick="window.print()" class="btn-outline">Print</button>
</x-filters>

<div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
    <x-stat label="Closing Debit" :value="cur($data['totals']['closing_dr'])" icon="trend" tone="blue" />
    <x-stat label="Closing Credit" :value="cur($data['totals']['closing_cr'])" icon="trenddown" tone="amber" />
    <x-stat :label="$data['balanced'] ? 'Tallied' : 'Difference'"
            :value="$data['balanced'] ? cur(0) : cur(abs($data['difference']))"
            :icon="$data['balanced'] ? 'check' : 'alert'"
            :tone="$data['balanced'] ? 'green' : 'rose'" />
</div>

<div class="card">
    <div class="card-head">
        <div>
            <h3 class="card-title">Trial Balance by Group</h3>
            <p class="text-xs text-ink-500">
                {{ \Carbon\Carbon::parse($from)->format('d M Y') }} to {{ \Carbon\Carbon::parse($to)->format('d M Y') }}
            </p>
        </div>
        <span class="{{ $data['balanced'] ? 'badge-green' : 'badge-rose' }}">
            {{ $data['balanced'] ? 'Balanced' : 'Out by ' . money_in(abs($data['difference'])) }}
        </span>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Group</th>
                    <th>Head</th>
                    <th class="text-right">Ledgers</th>
                    <th class="text-right">Debit</th>
                    <th class="text-right">Credit</th>
                    <th class="text-right">Closing Dr</th>
                    <th class="text-right">Closing Cr</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($data['rows'] as $r)
                    <tr>
                        <td class="font-mono text-xs text-ink-500">{{ $r['grcode'] }}</td>
                        <td class="font-medium">{{ $r['group'] }}</td>
                        <td>
                            <span class="{{ $r['bshead'] === 'BS' ? 'badge-blue' : 'badge-amber' }}">{{ $r['bshead'] }}</span>
                        </td>
                        <td class="num text-right text-ink-500">{{ $r['ledgers'] }}</td>
                        <td class="num text-right">{{ $r['debit'] ? money_in($r['debit']) : '—' }}</td>
                        <td class="num text-right">{{ $r['credit'] ? money_in($r['credit']) : '—' }}</td>
                        <td class="num text-right font-semibold">{{ $r['closing_dr'] ? money_in($r['closing_dr']) : '—' }}</td>
                        <td class="num text-right font-semibold">{{ $r['closing_cr'] ? money_in($r['closing_cr']) : '—' }}</td>
                        <td class="text-right">
                            <a href="{{ route('accounts.reports.group-summary', ['grcode' => $r['grcode'], 'from' => $from, 'to' => $to]) }}"
                               class="btn-outline btn-sm">Detail</a>
                        </td>
                    </tr>
                @empty
                    <x-empty :cols="9" message="No movement in this period" />
                @endforelse
            </tbody>
            @if (count($data['rows']))
                <tfoot>
                    <tr class="border-t-2 border-ink-300 bg-ink-50 font-bold dark:border-ink-700 dark:bg-ink-950/60">
                        <td colspan="4">Total</td>
                        <td class="num text-right">{{ money_in($data['totals']['debit']) }}</td>
                        <td class="num text-right">{{ money_in($data['totals']['credit']) }}</td>
                        <td class="num text-right">{{ money_in($data['totals']['closing_dr']) }}</td>
                        <td class="num text-right">{{ money_in($data['totals']['closing_cr']) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

@endsection
