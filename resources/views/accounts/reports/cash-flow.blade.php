@extends('layouts.app')
@section('title', 'Cash Flow Statement')
@section('subtitle', 'Day by day movement of the cash box and the bank accounts')

@section('content')

<x-filters :action="route('accounts.reports.cash-flow')">
    <div>
        <label class="label">From</label>
        <input type="date" name="from" value="{{ $from }}" class="input">
    </div>
    <div>
        <label class="label">To</label>
        <input type="date" name="to" value="{{ $to }}" class="input">
    </div>
    <button class="btn-primary">Apply</button>
    <a href="{{ route('accounts.reports.export', ['report' => 'cash-flow', 'from' => $from, 'to' => $to]) }}"
       class="btn-outline">Export CSV</a>
    <button type="button" onclick="window.print()" class="btn-outline">Print</button>
</x-filters>

<div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-4">
    <x-stat label="Opening" :value="cur($data['opening'])" icon="wallet" tone="slate" />
    <x-stat label="Inflow" :value="cur($data['total_inflow'])" icon="trend" tone="green" />
    <x-stat label="Outflow" :value="cur($data['total_outflow'])" icon="trenddown" tone="rose" />
    <x-stat label="Closing" :value="cur($data['closing'])" icon="bank" tone="brand" />
</div>

<div class="card">
    <div class="card-head">
        <h3 class="card-title">Cash Flow</h3>
        <span class="badge-gray">Cash &amp; bank ledgers</span>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th class="w-28">Date</th>
                    <th>Inflow details</th>
                    <th class="text-right">Inflow</th>
                    <th>Outflow details</th>
                    <th class="text-right">Outflow</th>
                    <th class="text-right">Closing</th>
                </tr>
            </thead>
            <tbody>
                <tr class="bg-ink-50 dark:bg-ink-950/50">
                    <td class="font-semibold">Opening</td>
                    <td colspan="4"></td>
                    <td class="num text-right font-semibold">{{ money_in($data['opening']) }}</td>
                </tr>

                @forelse ($data['days'] as $day)
                    <tr>
                        <td class="whitespace-nowrap font-medium">
                            {{ \Carbon\Carbon::parse($day['date'])->format('d M Y') }}
                        </td>
                        <td class="text-[12.5px] text-ink-600 dark:text-ink-400">
                            @foreach ($day['inflow'] as $name => $amount)
                                <div class="flex justify-between gap-3">
                                    <span>{{ $name }}</span>
                                    <span class="num">{{ money_in($amount) }}</span>
                                </div>
                            @endforeach
                        </td>
                        <td class="num text-right font-semibold text-emerald-700 dark:text-emerald-400">
                            {{ $day['in_total'] > 0 ? money_in($day['in_total']) : '—' }}
                        </td>
                        <td class="text-[12.5px] text-ink-600 dark:text-ink-400">
                            @foreach ($day['outflow'] as $name => $amount)
                                <div class="flex justify-between gap-3">
                                    <span>{{ $name }}</span>
                                    <span class="num">{{ money_in($amount) }}</span>
                                </div>
                            @endforeach
                        </td>
                        <td class="num text-right font-semibold text-rose-700 dark:text-rose-400">
                            {{ $day['out_total'] > 0 ? money_in($day['out_total']) : '—' }}
                        </td>
                        <td class="num text-right font-bold">{{ money_in($day['closing']) }}</td>
                    </tr>
                @empty
                    <x-empty :cols="6" message="No cash or bank movement in this period" />
                @endforelse
            </tbody>
            @if (count($data['days']))
                <tfoot>
                    <tr class="border-t-2 border-ink-300 bg-ink-50 font-bold dark:border-ink-700 dark:bg-ink-950/60">
                        <td colspan="2">Total</td>
                        <td class="num text-right">{{ money_in($data['total_inflow']) }}</td>
                        <td></td>
                        <td class="num text-right">{{ money_in($data['total_outflow']) }}</td>
                        <td class="num text-right">{{ money_in($data['closing']) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

@endsection
