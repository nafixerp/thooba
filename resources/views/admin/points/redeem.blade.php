@extends('layouts.app')
@section('title', 'Redeem Report')

@section('content')
<x-filters :action="route('admin.points.redeem-report')">
    <div><label class="label">From</label><input type="date" name="from" value="{{ $from }}" class="input"></div>
    <div><label class="label">To</label><input type="date" name="to" value="{{ $to }}" class="input"></div>
    <button class="btn-primary">Apply</button>
    <button type="button" onclick="window.print()" class="btn-outline">Print</button>
</x-filters>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Customer</th><th>Phone</th><th class="text-right">Points</th>
                <th class="text-right">Amount</th><th class="text-right">Gram</th><th>Date</th><th>Note</th><th>Status</th></tr></thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td><a href="{{ route('admin.customers.show', $r->customer_id) }}" class="font-medium hover:text-brand-600 hover:underline">{{ $r->customer->name ?? '—' }}</a></td>
                        <td>{{ $r->customer->phone ?? '' }}</td>
                        <td class="num font-semibold">{{ number_format($r->points, 0) }}</td>
                        <td class="num">{{ money_in($r->amount) }}</td>
                        <td class="num">{{ number_format($r->gram, 3) }}</td>
                        <td class="whitespace-nowrap">{{ $r->txn_date->format('d M Y') }}</td>
                        <td class="text-xs text-ink-500">{{ $r->note }}</td>
                        <td><x-status :value="$r->status" /></td>
                    </tr>
                @empty
                    <x-empty cols="8" message="No redemptions in this period" />
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($rows->hasPages())<div class="border-t border-ink-200 px-5 py-3 dark:border-ink-800">{{ $rows->links() }}</div>@endif
</div>
@endsection
