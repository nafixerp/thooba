@extends('layouts.app')
@section('title', 'Trial Balance')
@section('subtitle', 'Opening + period movement = closing, for every ledger')

@section('content')

<x-filters :action="route('accounts.reports.trial-balance')">
    <div>
        <label class="label">From</label>
        <input type="date" name="from" value="{{ $from }}" class="input">
    </div>
    <div>
        <label class="label">To</label>
        <input type="date" name="to" value="{{ $to }}" class="input">
    </div>
    <label class="flex items-center gap-2 pb-2 text-sm">
        <input type="checkbox" name="show_zero" value="1" {{ request()->boolean('show_zero') ? 'checked' : '' }}
               class="h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500 dark:border-ink-700 dark:bg-ink-900">
        Show zero-balance ledgers
    </label>
    <button class="btn-primary">Apply</button>
    <a href="{{ route('accounts.reports.export', ['report' => 'trial-balance', 'from' => $from, 'to' => $to]) }}" class="btn-outline">
        Export CSV
    </a>
    <button type="button" onclick="window.print()" class="btn-outline">Print</button>
</x-filters>

{{-- Balance check banner --}}
<div class="mb-4 flex flex-wrap items-center gap-3 rounded-xl border px-4 py-3
    {{ $data['balanced']
        ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-500/20 dark:bg-emerald-500/10'
        : 'border-rose-200 bg-rose-50 dark:border-rose-500/20 dark:bg-rose-500/10' }}">
    <svg class="h-5 w-5 {{ $data['balanced'] ? 'text-emerald-600' : 'text-rose-600' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        @if ($data['balanced'])
            <path d="M9 12l2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="9"/>
        @else
            <circle cx="12" cy="12" r="9"/><path d="M12 8v4m0 4h.01" stroke-linecap="round"/>
        @endif
    </svg>
    <span class="text-sm font-semibold {{ $data['balanced'] ? 'text-emerald-800 dark:text-emerald-300' : 'text-rose-800 dark:text-rose-300' }}">
        {{ $data['balanced'] ? 'Books are balanced — total debit equals total credit.' : 'Out of balance!' }}
    </span>
    @unless ($data['balanced'])
        <span class="font-mono text-sm text-rose-700 dark:text-rose-400">Difference: {{ money_in($data['difference']) }}</span>
    @endunless
    <span class="ml-auto text-xs text-ink-500">
        {{ \Carbon\Carbon::parse($from)->format('d M Y') }} — {{ \Carbon\Carbon::parse($to)->format('d M Y') }}
        · {{ count($data['rows']) }} ledgers
    </span>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th rowspan="2" class="align-bottom">Code</th>
                    <th rowspan="2" class="align-bottom">Ledger</th>
                    <th rowspan="2" class="align-bottom">Group</th>
                    <th colspan="2" class="text-center">Opening</th>
                    <th colspan="2" class="text-center">Transactions</th>
                    <th colspan="2" class="text-center">Closing</th>
                </tr>
                <tr>
                    <th class="text-right">Debit</th>
                    <th class="text-right">Credit</th>
                    <th class="text-right">Debit</th>
                    <th class="text-right">Credit</th>
                    <th class="text-right">Debit</th>
                    <th class="text-right">Credit</th>
                </tr>
            </thead>

            <tbody>
                @php $lastGroup = null; @endphp
                @forelse ($data['rows'] as $r)
                    @if ($r['group'] !== $lastGroup)
                        <tr class="bg-ink-100/70 dark:bg-ink-800/60">
                            <td colspan="9" class="px-4 py-2 text-[11px] font-bold uppercase tracking-wider text-ink-600 dark:text-ink-300">
                                {{ $r['base'] }} › {{ $r['group'] }}
                            </td>
                        </tr>
                        @php $lastGroup = $r['group']; @endphp
                    @endif
                    <tr>
                        <td class="font-mono text-xs text-ink-500">{{ $r['accode'] }}</td>
                        <td>
                            <a href="{{ route('accounts.reports.general-ledger', ['accode' => $r['accode'], 'from' => $from, 'to' => $to]) }}"
                               class="font-medium hover:text-brand-600 hover:underline">{{ $r['name'] }}</a>
                        </td>
                        <td class="text-xs text-ink-500">{{ $r['group'] }}</td>
                        <td class="num">{{ $r['opening_dr'] ? money_in($r['opening_dr']) : '' }}</td>
                        <td class="num">{{ $r['opening_cr'] ? money_in($r['opening_cr']) : '' }}</td>
                        <td class="num">{{ $r['debit'] ? money_in($r['debit']) : '' }}</td>
                        <td class="num">{{ $r['credit'] ? money_in($r['credit']) : '' }}</td>
                        <td class="num font-semibold">{{ $r['closing_dr'] ? money_in($r['closing_dr']) : '' }}</td>
                        <td class="num font-semibold">{{ $r['closing_cr'] ? money_in($r['closing_cr']) : '' }}</td>
                    </tr>
                @empty
                    <x-empty cols="9" message="No ledger activity in this period" />
                @endforelse
            </tbody>

            @if (count($data['rows']))
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-right">TOTAL</td>
                        <td class="num">{{ money_in($data['totals']['opening_dr']) }}</td>
                        <td class="num">{{ money_in($data['totals']['opening_cr']) }}</td>
                        <td class="num">{{ money_in($data['totals']['dr']) }}</td>
                        <td class="num">{{ money_in($data['totals']['cr']) }}</td>
                        <td class="num">{{ money_in($data['totals']['closing_dr']) }}</td>
                        <td class="num">{{ money_in($data['totals']['closing_cr']) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

@endsection
