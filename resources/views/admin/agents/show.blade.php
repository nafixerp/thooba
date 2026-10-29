@extends('layouts.app')
@section('title', $agent->name)
@section('subtitle', $agent->agent_code . ' · ' . $agent->phone)

@section('content')

<div class="mb-5 flex flex-wrap gap-2 no-print">
    <a href="{{ route('admin.agents.index') }}" class="btn-outline">&larr; Back</a>
    <a href="{{ route('admin.agents.edit', $agent) }}" class="btn-outline">Edit</a>
    @if ($agent->accode)
        <a href="{{ route('accounts.reports.general-ledger', ['accode' => $agent->accode]) }}" class="btn-dark">Ledger</a>
    @endif
</div>

@php
    $collected = $agent->collection();
    $earned = round($collected * $agent->commission_percent / 100, 2);
    $paid = (float) $agent->payouts->where('status', 'PAID')->sum('amount');
@endphp

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-stat label="Total Collected" :value="cur($collected)" icon="wallet" tone="brand" />
    <x-stat label="Commission Earned" :value="cur($earned)" :sub="$agent->commission_percent . '% rate'" icon="coins" tone="blue" />
    <x-stat label="Commission Paid" :value="cur($paid)" icon="check" tone="green" />
    <x-stat label="Pending" :value="cur($earned - $paid)" icon="clock" tone="amber" />
</div>

<div class="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-2">
    <div class="card">
        <div class="card-head"><h3 class="card-title">Customers ({{ $customers->count() }})</h3></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Reg No</th><th>Name</th><th>Phone</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($customers as $c)
                        <tr>
                            <td class="font-mono text-xs">{{ $c->reg_no }}</td>
                            <td><a href="{{ route('admin.customers.show', $c) }}" class="font-medium hover:text-brand-600 hover:underline">{{ $c->name }}</a></td>
                            <td>{{ $c->phone }}</td>
                            <td><x-status :value="$c->status ? 'ACTIVE' : 'CLOSED'" /></td>
                        </tr>
                    @empty
                        <x-empty cols="4" message="No customers assigned" />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h3 class="card-title">Commission Payouts</h3></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Period</th><th>Received</th><th>Mode</th><th class="text-right">Amount</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($agent->payouts as $p)
                        <tr>
                            <td class="whitespace-nowrap text-xs">{{ $p->from_date->format('d M y') }} — {{ $p->to_date->format('d M y') }}</td>
                            <td class="whitespace-nowrap">{{ $p->received_date?->format('d M Y') ?: '—' }}</td>
                            <td><span class="badge-gray">{{ $p->payment_mode }}</span></td>
                            <td class="num font-semibold">{{ money_in($p->amount) }}</td>
                            <td><x-status :value="$p->status" /></td>
                        </tr>
                    @empty
                        <x-empty cols="5" message="No payouts yet" />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-5 card">
    <div class="card-head"><h3 class="card-title">Recent Collections</h3></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Receipt</th><th>Customer</th><th>Date</th><th>Mode</th><th class="text-right">Total</th><th>Status</th></tr></thead>
            <tbody>
                @forelse ($payments as $p)
                    <tr>
                        <td><a href="{{ route('admin.payments.show', $p) }}" class="font-mono text-xs text-brand-600 hover:underline">{{ $p->receipt_no }}</a></td>
                        <td>{{ $p->customer->name ?? '—' }}</td>
                        <td class="whitespace-nowrap">{{ $p->payment_date->format('d M Y') }}</td>
                        <td><span class="badge-gray">{{ $p->payment_mode }}</span></td>
                        <td class="num font-semibold">{{ money_in($p->total) }}</td>
                        <td><x-status :value="$p->status" /></td>
                    </tr>
                @empty
                    <x-empty cols="6" message="No collections" />
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
