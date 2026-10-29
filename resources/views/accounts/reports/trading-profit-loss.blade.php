@extends('layouts.app')
@section('title', 'Trading, Profit & Loss A/c')
@section('subtitle', 'Trading account closing into gross profit, then the profit & loss account')

@section('content')

<x-filters :action="route('accounts.reports.trading-profit-loss')">
    <div>
        <label class="label">From</label>
        <input type="date" name="from" value="{{ $from }}" class="input" @disabled($upto)>
    </div>
    <div>
        <label class="label">{{ $upto ? 'Upto' : 'To' }}</label>
        <input type="date" name="to" value="{{ $to }}" class="input">
    </div>
    <div>
        <label class="label">Mode</label>
        <select name="upto" class="input" onchange="this.form.submit()">
            <option value="0" @selected(! $upto)>For the period</option>
            <option value="1" @selected($upto)>Upto date</option>
        </select>
    </div>
    <button class="btn-primary">Apply</button>
    <a href="{{ route('accounts.reports.export', ['report' => 'trading-profit-loss', 'from' => $from, 'to' => $to, 'upto' => (int) $upto]) }}"
       class="btn-outline">Export CSV</a>
    <button type="button" onclick="window.print()" class="btn-outline">Print</button>
</x-filters>

<div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
    <x-stat
        :label="$data['is_gross_profit'] ? 'Gross Profit' : 'Gross Loss'"
        :value="cur(abs($data['gross_profit']))"
        :icon="$data['is_gross_profit'] ? 'trend' : 'trenddown'"
        :tone="$data['is_gross_profit'] ? 'green' : 'rose'" />
    <x-stat
        label="Closing Stock"
        :value="cur($data['trading'][0]['closing_stock'] ?? 0)"
        sub="Valued figure used by both statements"
        icon="layers" tone="amber" />
    <x-stat
        :label="$data['is_profit'] ? 'Net Profit' : 'Net Loss'"
        :value="cur(abs($data['net_profit']))"
        :icon="$data['is_profit'] ? 'check' : 'alert'"
        :tone="$data['is_profit'] ? 'green' : 'rose'" />
</div>

@foreach ($data['trading'] as $section)
    <x-tpl-section
        :title="$section['title']"
        :period="$data['title']"
        :debit="$section['debit']"
        :credit="$section['credit']"
        :total-debit="$section['total_debit']"
        :total-credit="$section['total_credit']"
        debit-label="Dr — Cost of goods"
        credit-label="Cr — Sales &amp; stock"
        :from="$from" :to="$to" />
@endforeach

<x-tpl-section
    :title="$data['profit_loss']['title']"
    :period="$data['title']"
    :debit="$data['profit_loss']['debit']"
    :credit="$data['profit_loss']['credit']"
    :total-debit="$data['profit_loss']['total_debit']"
    :total-credit="$data['profit_loss']['total_credit']"
    debit-label="Dr — Expenses"
    credit-label="Cr — Income"
    :from="$from" :to="$to" />

<p class="mt-3 text-xs text-ink-500">
    Gross profit from the trading account{{ count($data['trading']) > 1 ? 's' : '' }} is brought down into the
    Profit &amp; Loss A/c, and the net {{ $data['is_profit'] ? 'profit' : 'loss' }} of
    <span class="font-medium">{{ cur(abs($data['net_profit'])) }}</span> is carried to the Balance Sheet.
    A ledger moves between these sections from
    <a href="{{ route('accounts.ledgers') }}" class="text-brand-600 hover:underline">Accounts &rarr; Ledgers</a>,
    and the closing stock figure is entered under
    <a href="{{ route('accounts.stock-values') }}" class="text-brand-600 hover:underline">Closing Stock</a>.
</p>

@endsection
