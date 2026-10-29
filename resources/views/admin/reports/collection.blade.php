@extends('layouts.app')
@section('title', 'Collection Report')

@section('content')
<x-filters :action="route('admin.reports.collection')">
    <div><label class="label">From</label><input type="date" name="from" value="{{ $from }}" class="input"></div>
    <div><label class="label">To</label><input type="date" name="to" value="{{ $to }}" class="input"></div>
    <button class="btn-primary">Apply</button>
    <button type="button" onclick="window.print()" class="btn-outline">Print</button>
</x-filters>

<div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
    <div class="card xl:col-span-2">
        <div class="card-head"><h3 class="card-title">Day-wise Collection</h3></div>
        <div class="table-wrap" style="max-height:560px;overflow-y:auto">
            <table class="table">
                <thead><tr><th>Date</th><th class="text-right">Count</th><th class="text-right">Amount</th>
                    <th class="text-right">GST</th><th class="text-right">Total</th><th class="text-right">Gram</th></tr></thead>
                <tbody>
                    @forelse ($daily as $d)
                        <tr>
                            <td class="whitespace-nowrap">{{ \Carbon\Carbon::parse($d->payment_date)->format('d M Y') }}</td>
                            <td class="num">{{ $d->c }}</td>
                            <td class="num">{{ money_in($d->amount) }}</td>
                            <td class="num text-ink-500">{{ money_in($d->tax) }}</td>
                            <td class="num font-semibold">{{ money_in($d->total) }}</td>
                            <td class="num">{{ number_format($d->gram, 3) }}</td>
                        </tr>
                    @empty
                        <x-empty cols="6" message="No collections in this period" />
                    @endforelse
                </tbody>
                @if ($daily->count())
                    <tfoot>
                        <tr>
                            <td class="text-right">TOTAL</td>
                            <td class="num">{{ $daily->sum('c') }}</td>
                            <td class="num">{{ money_in($daily->sum('amount')) }}</td>
                            <td class="num">{{ money_in($daily->sum('tax')) }}</td>
                            <td class="num">{{ money_in($daily->sum('total')) }}</td>
                            <td class="num">{{ number_format($daily->sum('gram'), 3) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <div class="space-y-5">
        <div class="card">
            <div class="card-head"><h3 class="card-title">By Payment Mode</h3></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Mode</th><th class="text-right">Count</th><th class="text-right">Total</th></tr></thead>
                    <tbody>
                        @forelse ($byMode as $m)
                            <tr>
                                <td><span class="badge-gray">{{ $m->payment_mode }}</span></td>
                                <td class="num">{{ $m->c }}</td>
                                <td class="num font-semibold">{{ money_in($m->total) }}</td>
                            </tr>
                        @empty
                            <x-empty cols="3" message="—" />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h3 class="card-title">By Agent</h3></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Agent</th><th class="text-right">Count</th><th class="text-right">Total</th></tr></thead>
                    <tbody>
                        @forelse ($byAgent as $a)
                            <tr>
                                <td>
                                    <div class="font-medium">{{ $a->name }}</div>
                                    <div class="font-mono text-xs text-ink-500">{{ $a->agent_code }}</div>
                                </td>
                                <td class="num">{{ $a->c }}</td>
                                <td class="num font-semibold">{{ money_in($a->total) }}</td>
                            </tr>
                        @empty
                            <x-empty cols="3" message="—" />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
