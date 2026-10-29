@extends('layouts.app')
@section('title', 'Joiners')
@section('subtitle', 'New customers and new scheme enrolments, by where they came from')

@php
    $badge = fn ($s) => match ($s) {
        'APP' => 'badge-green',
        'AGENT' => 'badge-blue',
        default => 'badge-gray',
    };
    $label = fn ($s) => match ($s) {
        'APP' => 'Mobile app',
        'AGENT' => 'Agent',
        default => 'Counter',
    };
    $cust = $summary['customers'];
    $enrl = $summary['enrolments'];
@endphp

@section('content')
<x-filters :action="route('admin.reports.joiners')">
    <div><label class="label">From</label><input type="date" name="from" value="{{ $from }}" class="input"></div>
    <div><label class="label">To</label><input type="date" name="to" value="{{ $to }}" class="input"></div>
    <div>
        <label class="label">Came in through</label>
        <select name="source" class="input">
            <option value="">All</option>
            <option value="APP" @selected($source === 'APP')>Mobile app</option>
            <option value="AGENT" @selected($source === 'AGENT')>Agent</option>
            <option value="ADMIN" @selected($source === 'ADMIN')>Counter</option>
        </select>
    </div>
    <button class="btn-primary">Apply</button>
    <button type="button" onclick="window.print()" class="btn-outline">Print</button>
</x-filters>

<div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-stat label="New customers" :value="number_format($cust->sum())" icon="users" tone="slate" />
    <x-stat label="From the app" :value="number_format($cust['APP'] ?? 0)" icon="phone" tone="green" />
    <x-stat label="New enrolments" :value="number_format($enrl->sum())" icon="layers" tone="brand" />
    <x-stat label="Enrolled in the app" :value="number_format($enrl['APP'] ?? 0)" icon="phone" tone="green" />
</div>

<div class="card mb-5">
    <div class="card-head">
        <h3 class="card-title">New customers</h3>
        <span class="text-sm text-ink-500">{{ $customers->total() }} in this range</span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Reg No</th><th>Name</th><th>Phone</th><th>Agent</th>
                <th>Came in through</th><th>Joined</th></tr></thead>
            <tbody>
                @forelse ($customers as $c)
                    <tr>
                        <td class="font-mono text-xs">{{ $c->reg_no }}</td>
                        <td><a href="{{ route('admin.customers.show', $c->id) }}" class="font-medium hover:text-brand-600 hover:underline">{{ $c->name }}</a></td>
                        <td class="font-mono text-xs">{{ $c->phone }}</td>
                        <td class="text-ink-500">{{ $c->agent->name ?? '—' }}</td>
                        <td><span class="{{ $badge($c->source) }}">{{ $label($c->source) }}</span></td>
                        <td class="whitespace-nowrap">{{ $c->created_at?->format('d M Y, h:i A') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-6 text-center text-sm text-ink-500">Nobody joined in this range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($customers->hasPages())<div class="card-pad">{{ $customers->links() }}</div>@endif
</div>

<div class="card">
    <div class="card-head">
        <h3 class="card-title">New scheme enrolments</h3>
        <span class="text-sm text-ink-500">{{ $enrolments->total() }} in this range</span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Book No</th><th>Scheme A/c</th><th>Customer</th><th>Scheme</th>
                <th class="text-right">Installment</th><th>Agent</th>
                <th>Came in through</th><th>Joined</th></tr></thead>
            <tbody>
                @forelse ($enrolments as $e)
                    <tr>
                        <td class="font-mono text-xs font-semibold">{{ $e->book_no ?: '—' }}</td>
                        <td class="font-mono text-xs text-ink-500">{{ $e->account_no }}</td>
                        <td><a href="{{ route('admin.customers.show', $e->customer_id) }}" class="font-medium hover:text-brand-600 hover:underline">{{ $e->customer->name ?? '—' }}</a></td>
                        <td>{{ $e->scheme->name ?? '—' }}</td>
                        <td class="num">{{ money_in($e->installment_amount) }}</td>
                        <td class="text-ink-500">{{ $e->agent->name ?? '—' }}</td>
                        <td><span class="{{ $badge($e->source) }}">{{ $label($e->source) }}</span></td>
                        <td class="whitespace-nowrap">{{ $e->created_at?->format('d M Y, h:i A') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="p-6 text-center text-sm text-ink-500">No scheme was taken in this range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($enrolments->hasPages())<div class="card-pad">{{ $enrolments->links() }}</div>@endif
</div>
@endsection
