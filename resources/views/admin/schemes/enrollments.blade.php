@extends('layouts.app')
@section('title', 'Scheme Enrollments')

@section('content')
<x-filters :action="route('admin.schemes.enrollments')">
    <div class="min-w-[200px] flex-1"><label class="label">Search</label><input name="q" value="{{ request('q') }}" class="input" placeholder="Account no, name or phone"></div>
    <div class="min-w-[180px]">
        <label class="label">Scheme</label>
        <select name="scheme_id" class="input">
            <option value="">All schemes</option>
            @foreach ($schemes as $s)<option value="{{ $s->id }}" @selected(request('scheme_id') == $s->id)>{{ $s->name }}</option>@endforeach
        </select>
    </div>
    <div class="min-w-[150px]">
        <label class="label">Status</label>
        <select name="status" class="input">
            <option value="">All</option>
            @foreach (['ACTIVE','MATURED','CLOSED','CANCELLED','DEFAULTED'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst(strtolower($s)) }}</option>
            @endforeach
        </select>
    </div>
    <button class="btn-primary">Filter</button>
</x-filters>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Account No</th><th>Customer</th><th>Scheme</th><th>Agent</th>
                <th class="text-right">Installment</th><th>Progress</th><th class="text-right">Paid</th>
                <th class="text-right">Gram</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td class="font-mono text-xs font-semibold">{{ $r->account_no }}</td>
                        <td>
                            <a href="{{ route('admin.customers.show', $r->customer_id) }}" class="font-medium hover:text-brand-600 hover:underline">{{ $r->customer->name ?? '—' }}</a>
                            <div class="text-xs text-ink-500">{{ $r->customer->phone ?? '' }}</div>
                        </td>
                        <td>{{ $r->scheme->name ?? '—' }}</td>
                        <td class="text-ink-600 dark:text-ink-400">{{ $r->agent->name ?? '—' }}</td>
                        <td class="num">{{ money_in($r->installment_amount) }}</td>
                        <td style="min-width:130px">
                            <div class="flex items-center gap-2">
                                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-ink-200 dark:bg-ink-800">
                                    <div class="h-full rounded-full bg-brand-500" style="width: {{ min($r->progress, 100) }}%"></div>
                                </div>
                                <span class="shrink-0 text-xs text-ink-500">{{ $r->paid_installments }}/{{ $r->total_installments }}</span>
                            </div>
                        </td>
                        <td class="num font-semibold">{{ money_in($r->total_paid) }}</td>
                        <td class="num">{{ number_format($r->total_gram, 3) }}</td>
                        <td><x-status :value="$r->status" /></td>
                        <td class="text-right">
                            <div class="flex justify-end gap-2">
                                @if (in_array($r->status, ['ACTIVE', 'MATURED']))
                                    <a href="{{ route('admin.schemes.enrollment', $r) }}#scheme-closure" class="btn-dark btn-sm">Finish / Close</a>
                                @endif
                                <a href="{{ route('admin.schemes.enrollment', $r) }}" class="btn-outline btn-sm">View</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-empty cols="10" message="No enrollments found" />
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($rows->hasPages())<div class="border-t border-ink-200 px-5 py-3 dark:border-ink-800">{{ $rows->links() }}</div>@endif
</div>
@endsection
