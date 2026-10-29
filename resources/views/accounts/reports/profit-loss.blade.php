@extends('layouts.app')
@section('title', 'Profit & Loss Account')
@section('subtitle', 'Income and expenses for the selected period')

@section('content')

<x-filters :action="route('accounts.reports.profit-loss')">
    <div>
        <label class="label">From</label>
        <input type="date" name="from" value="{{ $from }}" class="input">
    </div>
    <div>
        <label class="label">To</label>
        <input type="date" name="to" value="{{ $to }}" class="input">
    </div>
    <button class="btn-primary">Apply</button>
    <a href="{{ route('accounts.reports.export', ['report' => 'profit-loss', 'from' => $from, 'to' => $to]) }}" class="btn-outline">Export CSV</a>
    <button type="button" onclick="window.print()" class="btn-outline">Print</button>
</x-filters>

{{-- Summary --}}
<div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
    <x-stat label="Total Income" :value="cur($data['total_income'])" icon="trend" tone="green" />
    <x-stat label="Total Expense" :value="cur($data['total_expense'])" icon="trenddown" tone="rose" />
    <x-stat
        :label="$data['is_profit'] ? 'Net Profit' : 'Net Loss'"
        :value="cur(abs($data['net_profit']))"
        :sub="$data['total_income'] > 0 ? number_format(abs($data['net_profit']) / max($data['total_income'], 1) * 100, 1) . '% margin' : null"
        :icon="$data['is_profit'] ? 'check' : 'alert'"
        :tone="$data['is_profit'] ? 'green' : 'rose'" />
</div>

<div class="card">
    <div class="card-head">
        <div>
            <h3 class="card-title">Profit &amp; Loss Account</h3>
            <p class="text-xs text-ink-500">
                For the period {{ \Carbon\Carbon::parse($from)->format('d M Y') }}
                to {{ \Carbon\Carbon::parse($to)->format('d M Y') }}
            </p>
        </div>
        <span class="badge-gray">T-format</span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 lg:divide-x lg:divide-ink-200 dark:lg:divide-ink-800">

        {{-- ---------- DEBIT SIDE : EXPENSES ---------- --}}
        <div>
            <div class="border-b border-ink-200 bg-rose-50/60 px-5 py-2.5 dark:border-ink-800 dark:bg-rose-500/5">
                <h4 class="text-[11px] font-bold uppercase tracking-wider text-rose-700 dark:text-rose-400">
                    Dr — Expenses
                </h4>
            </div>

            <div class="divide-row">
                @forelse ($data['expense'] as $g)
                    <div x-data="{ o: false }">
                        <button @click="o = !o" class="flex w-full items-center gap-2 px-5 py-2.5 text-left hover:bg-ink-50 dark:hover:bg-ink-800/50">
                            <svg class="h-3 w-3 shrink-0 text-ink-400 transition-transform" :class="o && 'rotate-90'"
                                 fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                <path d="M9 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span class="flex-1 text-sm font-medium">{{ $g['group'] }}</span>
                            <span class="num font-semibold">{{ money_in($g['total']) }}</span>
                        </button>
                        <div x-show="o" x-cloak class="bg-ink-50/60 pb-1 dark:bg-ink-950/40">
                            @foreach ($g['accounts'] as $a)
                                <a href="{{ route('accounts.reports.general-ledger', ['accode' => $a['accode'], 'from' => $from, 'to' => $to]) }}"
                                   class="flex items-center gap-2 py-1.5 pl-12 pr-5 text-[13px] hover:text-brand-600">
                                    <span class="flex-1 text-ink-600 dark:text-ink-400">{{ $a['name'] }}</span>
                                    <span class="num text-ink-600 dark:text-ink-400">{{ money_in($a['balance']) }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-ink-500">No expenses recorded</p>
                @endforelse

                {{-- Net profit sits on the Dr side --}}
                @if ($data['is_profit'])
                    <div class="flex items-center gap-2 bg-emerald-50 px-5 py-2.5 dark:bg-emerald-500/10">
                        <span class="flex-1 text-sm font-bold text-emerald-800 dark:text-emerald-300">Net Profit c/d</span>
                        <span class="num font-bold text-emerald-800 dark:text-emerald-300">{{ money_in($data['net_profit']) }}</span>
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-2 border-t-2 border-ink-300 bg-ink-50 px-5 py-3 dark:border-ink-700 dark:bg-ink-950/60">
                <span class="flex-1 text-sm font-bold">Total</span>
                <span class="num text-[15px] font-bold">
                    {{ money_in($data['total_expense'] + max($data['net_profit'], 0)) }}
                </span>
            </div>
        </div>

        {{-- ---------- CREDIT SIDE : INCOME ---------- --}}
        <div>
            <div class="border-b border-ink-200 bg-emerald-50/60 px-5 py-2.5 dark:border-ink-800 dark:bg-emerald-500/5">
                <h4 class="text-[11px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
                    Cr — Income
                </h4>
            </div>

            <div class="divide-row">
                @forelse ($data['income'] as $g)
                    <div x-data="{ o: false }">
                        <button @click="o = !o" class="flex w-full items-center gap-2 px-5 py-2.5 text-left hover:bg-ink-50 dark:hover:bg-ink-800/50">
                            <svg class="h-3 w-3 shrink-0 text-ink-400 transition-transform" :class="o && 'rotate-90'"
                                 fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                <path d="M9 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span class="flex-1 text-sm font-medium">{{ $g['group'] }}</span>
                            <span class="num font-semibold">{{ money_in($g['total']) }}</span>
                        </button>
                        <div x-show="o" x-cloak class="bg-ink-50/60 pb-1 dark:bg-ink-950/40">
                            @foreach ($g['accounts'] as $a)
                                <a href="{{ route('accounts.reports.general-ledger', ['accode' => $a['accode'], 'from' => $from, 'to' => $to]) }}"
                                   class="flex items-center gap-2 py-1.5 pl-12 pr-5 text-[13px] hover:text-brand-600">
                                    <span class="flex-1 text-ink-600 dark:text-ink-400">{{ $a['name'] }}</span>
                                    <span class="num text-ink-600 dark:text-ink-400">{{ money_in($a['balance']) }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-ink-500">No income recorded</p>
                @endforelse

                @unless ($data['is_profit'])
                    <div class="flex items-center gap-2 bg-rose-50 px-5 py-2.5 dark:bg-rose-500/10">
                        <span class="flex-1 text-sm font-bold text-rose-800 dark:text-rose-300">Net Loss c/d</span>
                        <span class="num font-bold text-rose-800 dark:text-rose-300">{{ money_in(abs($data['net_profit'])) }}</span>
                    </div>
                @endunless
            </div>

            <div class="flex items-center gap-2 border-t-2 border-ink-300 bg-ink-50 px-5 py-3 dark:border-ink-700 dark:bg-ink-950/60">
                <span class="flex-1 text-sm font-bold">Total</span>
                <span class="num text-[15px] font-bold">
                    {{ money_in($data['total_income'] + max(-$data['net_profit'], 0)) }}
                </span>
            </div>
        </div>
    </div>
</div>

<p class="mt-3 text-xs text-ink-500">
    Net {{ $data['is_profit'] ? 'profit' : 'loss' }} is carried to the Balance Sheet under
    <span class="font-medium">Capital Account</span>.
</p>

@endsection
