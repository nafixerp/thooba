@extends('layouts.app')
@section('title', 'Payments')
@section('subtitle', 'Approved payments post to the daybook automatically')

@section('content')

<x-filters :action="route('admin.payments.index')">
    <div><label class="label">From</label><input type="date" name="from" value="{{ $from }}" class="input"></div>
    <div><label class="label">To</label><input type="date" name="to" value="{{ $to }}" class="input"></div>
    <div class="min-w-[140px]">
        <label class="label">Status</label>
        <select name="status" class="input">
            <option value="">All</option>
            @foreach (['PENDING','APPROVED','REJECTED'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst(strtolower($s)) }}</option>
            @endforeach
        </select>
    </div>
    <div class="min-w-[140px]">
        <label class="label">Mode</label>
        <select name="payment_mode" class="input">
            <option value="">All modes</option>
            @foreach (config('scheme.payment_modes') as $m)
                <option value="{{ $m }}" @selected(request('payment_mode') === $m)>{{ $m }}</option>
            @endforeach
        </select>
    </div>
    <div class="min-w-[170px]">
        <label class="label">Agent</label>
        <select name="agent_id" class="input">
            <option value="">All agents</option>
            @foreach ($agents as $a)<option value="{{ $a->id }}" @selected(request('agent_id') == $a->id)>{{ $a->name }}</option>@endforeach
        </select>
    </div>
    <div class="min-w-[180px] flex-1">
        <label class="label">Search</label>
        <input name="q" value="{{ request('q') }}" class="input" placeholder="Receipt, customer or txn ref">
    </div>
    <button class="btn-primary">Filter</button>
    <a href="{{ route('admin.payments.create') }}" class="btn-dark">+ New Payment</a>
</x-filters>

<div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
    <x-stat label="Records" :value="number_format($totals['count'])" icon="layers" tone="slate" />
    <x-stat label="Approved" :value="cur($totals['approved'])" icon="check" tone="green" />
    <x-stat label="Pending" :value="cur($totals['pending'])" icon="clock" tone="amber" />
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Receipt</th><th>Customer</th><th>Scheme</th><th>Date</th><th>Mode</th>
                    <th class="text-right">Amount</th><th class="text-right">GST</th><th class="text-right">Total</th>
                    <th class="text-right">Gram</th><th>Status</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payments as $p)
                    <tr>
                        <td><a href="{{ route('admin.payments.show', $p) }}" class="font-mono text-[12.5px] font-medium text-brand-600 hover:underline">{{ $p->receipt_no }}</a></td>
                        <td>
                            <div class="font-medium">{{ $p->customer->name ?? '—' }}</div>
                            <div class="text-xs text-ink-500">{{ $p->customer->reg_no ?? '' }} · {{ $p->customer->phone ?? '' }}</div>
                        </td>
                        <td class="font-mono text-xs text-ink-500">{{ $p->customerScheme->account_no ?? '—' }}</td>
                        <td class="whitespace-nowrap">{{ $p->payment_date->format('d M Y') }}</td>
                        <td><span class="badge-gray">{{ $p->payment_mode }}</span></td>
                        <td class="num">{{ money_in($p->amount) }}</td>
                        <td class="num text-ink-500">{{ money_in($p->tax) }}</td>
                        <td class="num font-semibold">{{ money_in($p->total) }}</td>
                        <td class="num">{{ number_format($p->gram, 3) }}</td>
                        <td><x-status :value="$p->status" /></td>
                        <td class="whitespace-nowrap text-right">
                            @if ($p->status === 'PENDING')
                                <form method="POST" action="{{ route('admin.payments.approve', $p) }}" class="inline">
                                    @csrf<button class="btn-success btn-sm">Approve</button>
                                </form>
                            @endif
                            <a href="{{ route('admin.payments.show', $p) }}" class="btn-outline btn-sm">View</a>
                        </td>
                    </tr>
                @empty
                    <x-empty cols="11" message="No payments in this period" />
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($payments->hasPages())
        <div class="border-t border-ink-200 px-5 py-3 dark:border-ink-800">{{ $payments->links() }}</div>
    @endif
</div>

@endsection
