@props([
    'title',
    'period' => null,
    'debit' => [],
    'credit' => [],
    'totalDebit' => 0,
    'totalCredit' => 0,
    'debitLabel' => 'Dr',
    'creditLabel' => 'Cr',
    'from' => null,
    'to' => null,
])

{{--
    One T account of the Trading / Profit & Loss statement.

    Computed lines - opening and closing stock, gross profit c/d and b/d, the
    net result - are shown in bold and are not links, because no ledger sits
    behind them: they are worked out at report time, the way dua soft rewrites
    OPSTOCK / CLSTOCK / GPROFIT / PROFIT on every run.
--}}

<div class="card mb-5">
    <div class="card-head">
        <div>
            <h3 class="card-title">{{ $title }}</h3>
            @if ($period)
                <p class="text-xs text-ink-500">{{ $period }}</p>
            @endif
        </div>
        <span class="badge-gray">T-format</span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 lg:divide-x lg:divide-ink-200 dark:lg:divide-ink-800">

        {{-- ---------- DEBIT ---------- --}}
        <div>
            <div class="border-b border-ink-200 bg-rose-50/60 px-5 py-2.5 dark:border-ink-800 dark:bg-rose-500/5">
                <h4 class="text-[11px] font-bold uppercase tracking-wider text-rose-700 dark:text-rose-400">
                    {!! $debitLabel !!}
                </h4>
            </div>

            <div class="divide-row">
                @forelse ($debit as $line)
                    @if ($line['computed'] ?? false)
                        <div class="flex items-center gap-2 px-5 py-2.5 {{ ($line['bold'] ?? false) ? 'bg-emerald-50 dark:bg-emerald-500/10' : '' }}">
                            <span class="flex-1 text-sm {{ ($line['bold'] ?? false) ? 'font-bold text-emerald-800 dark:text-emerald-300' : 'font-medium' }}">
                                To {{ $line['name'] }}
                            </span>
                            <span class="num {{ ($line['bold'] ?? false) ? 'font-bold text-emerald-800 dark:text-emerald-300' : 'font-semibold' }}">
                                {{ money_in($line['amount']) }}
                            </span>
                        </div>
                    @else
                        <a href="{{ route('accounts.reports.general-ledger', ['accode' => $line['accode'], 'from' => $from, 'to' => $to]) }}"
                           class="flex items-center gap-2 px-5 py-2.5 hover:bg-ink-50 dark:hover:bg-ink-800/50">
                            <span class="flex-1 text-sm">
                                To {{ $line['name'] }}
                                @if (! empty($line['group']))
                                    <span class="ml-1 text-[11px] text-ink-500">{{ $line['group'] }}</span>
                                @endif
                            </span>
                            <span class="num font-semibold">{{ money_in($line['amount']) }}</span>
                        </a>
                    @endif
                @empty
                    <p class="px-5 py-10 text-center text-sm text-ink-500">Nothing on this side</p>
                @endforelse
            </div>

            <div class="flex items-center gap-2 border-t-2 border-ink-300 bg-ink-50 px-5 py-3 dark:border-ink-700 dark:bg-ink-950/60">
                <span class="flex-1 text-sm font-bold">Total</span>
                <span class="num text-[15px] font-bold">{{ money_in($totalDebit) }}</span>
            </div>
        </div>

        {{-- ---------- CREDIT ---------- --}}
        <div>
            <div class="border-b border-ink-200 bg-emerald-50/60 px-5 py-2.5 dark:border-ink-800 dark:bg-emerald-500/5">
                <h4 class="text-[11px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
                    {!! $creditLabel !!}
                </h4>
            </div>

            <div class="divide-row">
                @forelse ($credit as $line)
                    @if ($line['computed'] ?? false)
                        <div class="flex items-center gap-2 px-5 py-2.5 {{ ($line['bold'] ?? false) ? 'bg-rose-50 dark:bg-rose-500/10' : '' }}">
                            <span class="flex-1 text-sm {{ ($line['bold'] ?? false) ? 'font-bold text-rose-800 dark:text-rose-300' : 'font-medium' }}">
                                By {{ $line['name'] }}
                            </span>
                            <span class="num {{ ($line['bold'] ?? false) ? 'font-bold text-rose-800 dark:text-rose-300' : 'font-semibold' }}">
                                {{ money_in($line['amount']) }}
                            </span>
                        </div>
                    @else
                        <a href="{{ route('accounts.reports.general-ledger', ['accode' => $line['accode'], 'from' => $from, 'to' => $to]) }}"
                           class="flex items-center gap-2 px-5 py-2.5 hover:bg-ink-50 dark:hover:bg-ink-800/50">
                            <span class="flex-1 text-sm">
                                By {{ $line['name'] }}
                                @if (! empty($line['group']))
                                    <span class="ml-1 text-[11px] text-ink-500">{{ $line['group'] }}</span>
                                @endif
                            </span>
                            <span class="num font-semibold">{{ money_in($line['amount']) }}</span>
                        </a>
                    @endif
                @empty
                    <p class="px-5 py-10 text-center text-sm text-ink-500">Nothing on this side</p>
                @endforelse
            </div>

            <div class="flex items-center gap-2 border-t-2 border-ink-300 bg-ink-50 px-5 py-3 dark:border-ink-700 dark:bg-ink-950/60">
                <span class="flex-1 text-sm font-bold">Total</span>
                <span class="num text-[15px] font-bold">{{ money_in($totalCredit) }}</span>
            </div>
        </div>
    </div>
</div>
