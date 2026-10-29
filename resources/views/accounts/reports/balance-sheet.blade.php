@extends('layouts.app')
@section('title', 'Balance Sheet')
@section('subtitle', 'Assets = Liabilities + Capital, as on a date')

@section('content')

<x-filters :action="route('accounts.reports.balance-sheet')">
    <div>
        <label class="label">As on</label>
        <input type="date" name="as_on" value="{{ $asOn }}" class="input">
    </div>
    <button class="btn-primary">Apply</button>
    <a href="{{ route('accounts.reports.export', ['report' => 'balance-sheet', 'as_on' => $asOn]) }}" class="btn-outline">Export CSV</a>
    <button type="button" onclick="window.print()" class="btn-outline">Print</button>
</x-filters>

{{-- Balance check --}}
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
        {{ $data['balanced'] ? 'Balance Sheet tallies.' : 'Balance Sheet does not tally.' }}
    </span>
    @unless ($data['balanced'])
        <span class="font-mono text-sm text-rose-700 dark:text-rose-400">Difference: {{ money_in($data['difference']) }}</span>
    @endunless
    <span class="ml-auto text-xs text-ink-500">As on {{ \Carbon\Carbon::parse($asOn)->format('d M Y') }}</span>
</div>

<div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
    <x-stat label="Total Assets" :value="cur($data['total_assets'])" icon="bank" tone="blue" />
    <x-stat label="Liabilities + Capital" :value="cur($data['total_liabilities'])" icon="coins" tone="violet" />
    <x-stat
        :label="$data['net_profit'] >= 0 ? 'Current Year Profit' : 'Current Year Loss'"
        :value="cur(abs($data['net_profit']))"
        :icon="$data['net_profit'] >= 0 ? 'trend' : 'trenddown'"
        :tone="$data['net_profit'] >= 0 ? 'green' : 'rose'"
        :href="route('accounts.reports.profit-loss')" />
</div>

<div class="card">
    <div class="card-head">
        <h3 class="card-title">Balance Sheet as on {{ \Carbon\Carbon::parse($asOn)->format('d M Y') }}</h3>
        <span class="badge-gray">T-format</span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 lg:divide-x lg:divide-ink-200 dark:lg:divide-ink-800">

        {{-- ---------- LIABILITIES ---------- --}}
        <div>
            <div class="border-b border-ink-200 bg-violet-50/60 px-5 py-2.5 dark:border-ink-800 dark:bg-violet-500/5">
                <h4 class="text-[11px] font-bold uppercase tracking-wider text-violet-700 dark:text-violet-400">
                    Liabilities &amp; Capital
                </h4>
            </div>

            <div class="divide-row">
                @forelse ($data['liabilities'] as $g)
                    <div x-data="{ o: false }">
                        <button @click="o = !o" class="flex w-full items-center gap-2 px-5 py-2.5 text-left hover:bg-ink-50 dark:hover:bg-ink-800/50">
                            @if (empty($g['is_computed']))
                                <svg class="h-3 w-3 shrink-0 text-ink-400 transition-transform" :class="o && 'rotate-90'"
                                     fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                    <path d="M9 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            @else
                                <span class="h-3 w-3 shrink-0"></span>
                            @endif
                            <span class="flex-1 text-sm font-medium {{ ! empty($g['is_computed']) ? 'text-emerald-700 dark:text-emerald-400' : '' }}">
                                {{ $g['group'] }}
                            </span>
                            <span class="num font-semibold">{{ money_in($g['total']) }}</span>
                        </button>
                        @if (! empty($g['accounts']))
                            <div x-show="o" x-cloak class="bg-ink-50/60 pb-1 dark:bg-ink-950/40">
                                @foreach ($g['accounts'] as $a)
                                    <a href="{{ route('accounts.reports.general-ledger', ['accode' => $a['accode'], 'to' => $asOn]) }}"
                                       class="flex items-center gap-2 py-1.5 pl-12 pr-5 text-[13px] hover:text-brand-600">
                                        <span class="flex-1 text-ink-600 dark:text-ink-400">{{ $a['name'] }}</span>
                                        <span class="num text-ink-600 dark:text-ink-400">{{ money_in($a['balance']) }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-ink-500">No liabilities</p>
                @endforelse
            </div>

            <div class="flex items-center gap-2 border-t-2 border-ink-300 bg-ink-50 px-5 py-3 dark:border-ink-700 dark:bg-ink-950/60">
                <span class="flex-1 text-sm font-bold">Total</span>
                <span class="num text-[15px] font-bold">{{ money_in($data['total_liabilities']) }}</span>
            </div>
        </div>

        {{-- ---------- ASSETS ---------- --}}
        <div>
            <div class="border-b border-ink-200 bg-sky-50/60 px-5 py-2.5 dark:border-ink-800 dark:bg-sky-500/5">
                <h4 class="text-[11px] font-bold uppercase tracking-wider text-sky-700 dark:text-sky-400">Assets</h4>
            </div>

            <div class="divide-row">
                @forelse ($data['assets'] as $g)
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
                                <a href="{{ route('accounts.reports.general-ledger', ['accode' => $a['accode'], 'to' => $asOn]) }}"
                                   class="flex items-center gap-2 py-1.5 pl-12 pr-5 text-[13px] hover:text-brand-600">
                                    <span class="flex-1 text-ink-600 dark:text-ink-400">{{ $a['name'] }}</span>
                                    <span class="num text-ink-600 dark:text-ink-400">{{ money_in($a['balance']) }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-ink-500">No assets</p>
                @endforelse
            </div>

            <div class="flex items-center gap-2 border-t-2 border-ink-300 bg-ink-50 px-5 py-3 dark:border-ink-700 dark:bg-ink-950/60">
                <span class="flex-1 text-sm font-bold">Total</span>
                <span class="num text-[15px] font-bold">{{ money_in($data['total_assets']) }}</span>
            </div>
        </div>
    </div>
</div>

@endsection
