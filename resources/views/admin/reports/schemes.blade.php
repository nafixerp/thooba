@extends('layouts.app')
@section('title', 'Scheme Report')

@section('content')
<x-filters :action="route('admin.reports.schemes')">
    <div class="min-w-[200px]">
        <label class="label">Scheme</label>
        <select name="scheme_id" class="input">
            <option value="">All schemes</option>
            @foreach ($schemes as $s)<option value="{{ $s->id }}" @selected(request('scheme_id') == $s->id)>{{ $s->name }}</option>@endforeach
        </select>
    </div>
    <div class="min-w-[140px]">
        <label class="label">Book No</label>
        <input name="book_no" class="input" value="{{ request('book_no') }}" placeholder="e.g. 207">
    </div>
    <div class="min-w-[150px]">
        <label class="label">Status</label>
        <select name="status" class="input">
            <option value="">All</option>
            @foreach (['ACTIVE','MATURED','CLOSED','CANCELLED'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst(strtolower($s)) }}</option>
            @endforeach
        </select>
    </div>
    <button class="btn-primary">Apply</button>
    <button type="button" onclick="window.print()" class="btn-outline">Print</button>
</x-filters>

<div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-4">
    <x-stat label="Enrollments" :value="number_format($summary->c ?? 0)" icon="layers" tone="slate" />
    <x-stat label="Total Paid" :value="cur($summary->paid ?? 0)" icon="wallet" tone="brand" />
    <x-stat label="GST" :value="cur($summary->gst ?? 0)" icon="coins" tone="blue" />
    <x-stat label="Gram" :value="number_format($summary->gram ?? 0, 3) . ' g'" icon="gem" tone="amber" />
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Book No</th><th>Reg No</th><th>Customer</th><th>Phone</th><th>Scheme</th>
                <th class="text-right">Total</th><th class="text-right">GST</th><th class="text-right">Gram</th>
                <th class="text-right">Count</th><th>Status</th></tr></thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td class="font-mono text-xs font-semibold">{{ $r->book_no ?: '—' }}</td>
                        <td class="font-mono text-xs">{{ $r->customer->reg_no ?? '' }}</td>
                        <td><a href="{{ route('admin.customers.show', $r->customer_id) }}" class="font-medium hover:text-brand-600 hover:underline">{{ $r->customer->name ?? '—' }}</a></td>
                        <td>{{ $r->customer->phone ?? '' }}</td>
                        <td>{{ $r->scheme->name ?? '—' }}</td>
                        <td class="num font-semibold">{{ money_in($r->total_paid) }}</td>
                        <td class="num text-ink-500">{{ money_in($r->total_gst) }}</td>
                        <td class="num">{{ number_format($r->total_gram, 3) }}</td>
                        <td class="num">{{ $r->paid_installments }}/{{ $r->total_installments }}</td>
                        <td><x-status :value="$r->status" /></td>
                    </tr>
                @empty
                    <x-empty cols="9" message="No enrollments" />
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($rows->hasPages())<div class="border-t border-ink-200 px-5 py-3 dark:border-ink-800">{{ $rows->links() }}</div>@endif
</div>
@endsection
