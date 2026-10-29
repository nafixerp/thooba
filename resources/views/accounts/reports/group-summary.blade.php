@extends('layouts.app')
@section('title', 'Group Account Summary')
@section('subtitle', 'Opening, movement and closing for every ledger in one account group')

@section('content')

<x-filters :action="route('accounts.reports.group-summary')">
    <div class="min-w-[240px]">
        <label class="label">Account group</label>
        <select name="grcode" class="input" onchange="this.form.submit()">
            @foreach ($groups as $g)
                <option value="{{ $g->grcode }}" @selected($grcode === $g->grcode)>{{ $g->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="label">From</label>
        <input type="date" name="from" value="{{ $from }}" class="input">
    </div>
    <div>
        <label class="label">To</label>
        <input type="date" name="to" value="{{ $to }}" class="input">
    </div>
    <button class="btn-primary">Apply</button>
    <a href="{{ route('accounts.reports.export', ['report' => 'group-summary', 'grcode' => $grcode, 'from' => $from, 'to' => $to]) }}"
       class="btn-outline">Export CSV</a>
    <button type="button" onclick="window.print()" class="btn-outline">Print</button>
</x-filters>

<div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-4">
    <x-stat label="Ledgers" :value="number_format(count($data['rows']))" icon="users" tone="slate" />
    <x-stat label="Opening" :value="cur(abs($data['totals']['opening']))"
            :sub="$data['totals']['opening'] >= 0 ? 'Dr' : 'Cr'" icon="wallet" tone="violet" />
    <x-stat label="Debit" :value="cur($data['totals']['dr'])" icon="trend" tone="blue" />
    <x-stat label="Credit" :value="cur($data['totals']['cr'])" icon="trenddown" tone="amber" />
</div>

<div class="card">
    <div class="card-head">
        <div>
            <h3 class="card-title">{{ $data['group'] }}</h3>
            <p class="text-xs text-ink-500">
                {{ \Carbon\Carbon::parse($from)->format('d M Y') }} to {{ \Carbon\Carbon::parse($to)->format('d M Y') }}
            </p>
        </div>
        <span class="badge-gray">{{ $data['grcode'] }}</span>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Ledger</th>
                    <th>Phone</th>
                    <th class="text-right">Opening</th>
                    <th class="text-right">Debit</th>
                    <th class="text-right">Credit</th>
                    <th class="text-right">Closing</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($data['rows'] as $r)
                    <tr>
                        <td class="font-mono text-xs text-ink-500">{{ $r['accode'] }}</td>
                        <td class="font-medium">{{ $r['name'] }}</td>
                        <td class="text-ink-600 dark:text-ink-400">{{ $r['phone'] ?: '—' }}</td>
                        <td class="num text-right">
                            {{ money_in(abs($r['opening'])) }}
                            <span class="ml-0.5 text-[10px] text-ink-500">{{ $r['opening_side'] }}</span>
                        </td>
                        <td class="num text-right">{{ $r['debit'] ? money_in($r['debit']) : '—' }}</td>
                        <td class="num text-right">{{ $r['credit'] ? money_in($r['credit']) : '—' }}</td>
                        <td class="num text-right font-semibold">
                            {{ money_in(abs($r['closing'])) }}
                            <span class="ml-0.5 text-[10px] text-ink-500">{{ $r['closing_side'] }}</span>
                        </td>
                        <td class="text-right">
                            <a href="{{ route('accounts.reports.general-ledger', ['accode' => $r['accode'], 'from' => $from, 'to' => $to]) }}"
                               class="btn-outline btn-sm">Ledger</a>
                        </td>
                    </tr>
                @empty
                    <x-empty :cols="8" message="No ledger in this group has moved in the period" />
                @endforelse
            </tbody>
            @if (count($data['rows']))
                <tfoot>
                    <tr class="border-t-2 border-ink-300 bg-ink-50 font-bold dark:border-ink-700 dark:bg-ink-950/60">
                        <td colspan="3">Total</td>
                        <td class="num text-right">{{ money_in(abs($data['totals']['opening'])) }}</td>
                        <td class="num text-right">{{ money_in($data['totals']['dr']) }}</td>
                        <td class="num text-right">{{ money_in($data['totals']['cr']) }}</td>
                        <td class="num text-right">{{ money_in(abs($data['totals']['closing'])) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

@endsection
