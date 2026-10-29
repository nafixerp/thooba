@extends('layouts.app')
@section('title', 'Customer Report')
@section('subtitle', 'Payment-wise customer collection')

@section('content')
<x-filters :action="route('admin.reports.customers')">
    <div><label class="label">From</label><input type="date" name="from" value="{{ $from }}" class="input"></div>
    <div><label class="label">To</label><input type="date" name="to" value="{{ $to }}" class="input"></div>
    <button class="btn-primary">Apply</button>
    <button type="button" onclick="window.print()" class="btn-outline">Print</button>
</x-filters>

<div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
    <x-stat label="Payments" :value="number_format($summary->c ?? 0)" icon="layers" tone="slate" />
    <x-stat label="Amount" :value="cur($summary->amount ?? 0)" icon="wallet" tone="brand" />
    <x-stat label="GST" :value="cur($summary->tax ?? 0)" icon="coins" tone="blue" />
    <x-stat label="Total" :value="cur($summary->total ?? 0)" icon="check" tone="green" />
    <x-stat label="Gram" :value="number_format($summary->gram ?? 0, 3) . ' g'" icon="gem" tone="amber" />
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Reg No</th><th>Customer</th><th>Scheme A/c</th>
                <th class="text-right">Amount</th><th class="text-right">GST</th><th class="text-right">Total</th>
                <th class="text-right">Gram</th><th>Date</th><th>Type</th><th>Mode</th></tr></thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td class="font-mono text-xs">{{ $r->customer->reg_no ?? '' }}</td>
                        <td><a href="{{ route('admin.customers.show', $r->customer_id) }}" class="font-medium hover:text-brand-600 hover:underline">{{ $r->customer->name ?? '—' }}</a></td>
                        <td class="font-mono text-xs text-ink-500">{{ $r->customerScheme->account_no ?? '—' }}</td>
                        <td class="num">{{ money_in($r->amount) }}</td>
                        <td class="num text-ink-500">{{ money_in($r->tax) }}</td>
                        <td class="num font-semibold">{{ money_in($r->total) }}</td>
                        <td class="num">{{ number_format($r->gram, 3) }}</td>
                        <td class="whitespace-nowrap">{{ $r->payment_date->format('d M Y') }}</td>
                        <td><span class="badge-gray">{{ $r->payment_type }}</span></td>
                        <td><span class="badge-gray">{{ $r->payment_mode }}</span></td>
                    </tr>
                @empty
                    <x-empty cols="10" message="No payments in this period" />
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($rows->hasPages())<div class="border-t border-ink-200 px-5 py-3 dark:border-ink-800">{{ $rows->links() }}</div>@endif
</div>
@endsection
