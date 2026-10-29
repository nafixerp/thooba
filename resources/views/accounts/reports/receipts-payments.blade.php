@extends('layouts.app')
@section('title', 'Receipts & Payments')
@section('subtitle', 'Every cash and bank voucher for the period, with the account on the other side')

@section('content')

<x-filters :action="route('accounts.reports.receipts-payments')">
    <div>
        <label class="label">From</label>
        <input type="date" name="from" value="{{ $from }}" class="input">
    </div>
    <div>
        <label class="label">To</label>
        <input type="date" name="to" value="{{ $to }}" class="input">
    </div>
    <button class="btn-primary">Apply</button>
    <a href="{{ route('accounts.reports.export', ['report' => 'receipts-payments', 'from' => $from, 'to' => $to]) }}"
       class="btn-outline">Export CSV</a>
    <button type="button" onclick="window.print()" class="btn-outline">Print</button>
</x-filters>

<div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-4">
    <x-stat label="Opening" :value="cur($data['opening'])" icon="wallet" tone="slate" />
    <x-stat label="Receipts" :value="cur($data['total_receipts'])" :sub="count($data['receipts']) . ' vouchers'" icon="trend" tone="green" />
    <x-stat label="Payments" :value="cur($data['total_payments'])" :sub="count($data['payments']) . ' vouchers'" icon="trenddown" tone="rose" />
    <x-stat label="Closing" :value="cur($data['closing'])" icon="bank" tone="brand" />
</div>

<div class="card">
    <div class="card-head">
        <h3 class="card-title">Receipts &amp; Payments</h3>
        <span class="badge-gray">T-format</span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 lg:divide-x lg:divide-ink-200 dark:lg:divide-ink-800">

        {{-- Tailwind only ships the class names it can see, so both palettes are
             written out in full rather than built from a variable. --}}
        @foreach ([
            [
                'key' => 'receipts', 'label' => 'Dr — Receipts', 'prefix' => 'To',
                'total' => $data['total_receipts'],
                'head' => 'border-b border-ink-200 bg-emerald-50/60 px-5 py-2.5 dark:border-ink-800 dark:bg-emerald-500/5',
                'text' => 'text-[11px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400',
            ],
            [
                'key' => 'payments', 'label' => 'Cr — Payments', 'prefix' => 'By',
                'total' => $data['total_payments'],
                'head' => 'border-b border-ink-200 bg-rose-50/60 px-5 py-2.5 dark:border-ink-800 dark:bg-rose-500/5',
                'text' => 'text-[11px] font-bold uppercase tracking-wider text-rose-700 dark:text-rose-400',
            ],
        ] as $side)
            <div>
                <div class="{{ $side['head'] }}">
                    <h4 class="{{ $side['text'] }}">{{ $side['label'] }}</h4>
                </div>

                <div class="divide-row max-h-[540px] overflow-y-auto">
                    @forelse ($data[$side['key']] as $r)
                        <div class="flex items-start gap-3 px-5 py-2.5">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ $side['prefix'] }} {{ $r['party'] }}</p>
                                <p class="truncate text-[11.5px] text-ink-500">
                                    {{ $r['date'] }} · {{ $r['voucher_type'] }} {{ $r['voucher_no'] }} · {{ $r['account'] }}
                                    @if ($r['narration']) · {{ $r['narration'] }} @endif
                                </p>
                            </div>
                            <span class="num shrink-0 font-semibold">{{ money_in($r['amount']) }}</span>
                        </div>
                    @empty
                        <p class="px-5 py-10 text-center text-sm text-ink-500">Nothing on this side</p>
                    @endforelse
                </div>

                <div class="flex items-center gap-2 border-t-2 border-ink-300 bg-ink-50 px-5 py-3 dark:border-ink-700 dark:bg-ink-950/60">
                    <span class="flex-1 text-sm font-bold">Total</span>
                    <span class="num text-[15px] font-bold">{{ money_in($side['total']) }}</span>
                </div>
            </div>
        @endforeach
    </div>
</div>

@endsection
