@extends('layouts.app')
@section('title', $title ?? 'General Ledger')
@section('subtitle', 'Date-wise statement with running balance')

@section('content')

<x-filters :action="url()->current()">
    <div class="min-w-[240px] flex-1">
        <label class="label">Ledger Account</label>
        <select name="accode" class="input" onchange="this.form.submit()">
            @foreach ($ledgers as $l)
                <option value="{{ $l->accode }}" @selected($accode === $l->accode)>
                    {{ $l->accode }} — {{ $l->name }}
                </option>
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
    <a href="{{ route('accounts.reports.export', ['report' => 'general-ledger', 'accode' => $accode, 'from' => $from, 'to' => $to]) }}" class="btn-outline">Export CSV</a>
    <button type="button" onclick="window.print()" class="btn-outline">Print</button>
</x-filters>

<div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-stat label="Opening Balance" :value="money_in(abs($data['opening'])) . ' ' . $data['opening_side']" icon="clock" tone="slate" />
    <x-stat label="Total Debit" :value="cur($data['total_debit'])" icon="trend" tone="blue" />
    <x-stat label="Total Credit" :value="cur($data['total_credit'])" icon="trenddown" tone="violet" />
    <x-stat label="Closing Balance"
            :value="money_in(abs($data['closing'])) . ' ' . $data['closing_side']"
            :tone="$data['closing'] >= 0 ? 'green' : 'amber'" icon="check" />
</div>

<div class="card">
    <div class="card-head">
        <div>
            <h3 class="card-title">{{ $data['account']->name }}</h3>
            <p class="text-xs text-ink-500">
                {{ $data['account']->accode }} ·
                {{ $data['account']->group?->name }} ·
                {{ $data['account']->bshead === 'BS' ? 'Balance Sheet' : 'Profit & Loss' }}
            </p>
        </div>
        <span class="text-xs text-ink-500">
            {{ \Carbon\Carbon::parse($from)->format('d M Y') }} — {{ \Carbon\Carbon::parse($to)->format('d M Y') }}
        </span>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Particulars</th>
                    <th>Voucher</th>
                    <th>Remarks</th>
                    <th class="text-right">Debit</th>
                    <th class="text-right">Credit</th>
                    <th class="text-right">Balance</th>
                </tr>
            </thead>
            <tbody>
                <tr class="bg-ink-50/70 dark:bg-ink-800/40">
                    <td colspan="4" class="font-medium">Opening Balance</td>
                    <td class="num">{{ $data['opening'] > 0 ? money_in($data['opening']) : '' }}</td>
                    <td class="num">{{ $data['opening'] < 0 ? money_in(abs($data['opening'])) : '' }}</td>
                    <td class="num font-semibold">{{ money_in(abs($data['opening'])) }} {{ $data['opening_side'] }}</td>
                </tr>

                @forelse ($data['lines'] as $l)
                    <tr>
                        <td class="whitespace-nowrap text-ink-600 dark:text-ink-400">{{ $l['date'] }}</td>
                        <td class="font-medium">{{ $l['particulars'] }}</td>
                        <td>
                            <a href="{{ route('accounts.vouchers.show', $l['sno']) }}" class="text-xs text-brand-600 hover:underline">
                                <span class="badge-gray">{{ $l['voucher_type'] }}</span>
                                <span class="ml-1 font-mono">{{ $l['voucher_no'] }}</span>
                            </a>
                        </td>
                        <td class="max-w-[280px] truncate text-xs text-ink-500">{{ $l['remarks'] }}</td>
                        <td class="num">{{ $l['debit'] ? money_in($l['debit']) : '' }}</td>
                        <td class="num">{{ $l['credit'] ? money_in($l['credit']) : '' }}</td>
                        <td class="num font-semibold">{{ money_in(abs($l['balance'])) }} {{ $l['balance_side'] }}</td>
                    </tr>
                @empty
                    <x-empty cols="7" message="No transactions in this period" />
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-right">TOTAL</td>
                    <td class="num">{{ money_in($data['total_debit']) }}</td>
                    <td class="num">{{ money_in($data['total_credit']) }}</td>
                    <td class="num">{{ money_in(abs($data['closing'])) }} {{ $data['closing_side'] }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

@endsection
