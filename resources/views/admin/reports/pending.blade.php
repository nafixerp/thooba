@extends('layouts.app')
@section('title', 'Payment Pending')
@section('subtitle', 'Installments due or overdue')

@section('content')
<x-filters :action="route('admin.reports.pending')">
    <div><label class="label">As on</label><input type="date" name="as_on" value="{{ request('as_on', now()->toDateString()) }}" class="input"></div>
    <button class="btn-primary">Apply</button>
    <button type="button" onclick="window.print()" class="btn-outline">Print</button>
</x-filters>

<div class="mb-5">
    <x-stat label="Total Outstanding" :value="cur($total)" icon="alert" tone="rose" />
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Reg No</th><th>Customer</th><th>Phone</th><th>Scheme</th><th>Inst #</th>
                <th>Due Date</th><th class="text-right">Amount</th><th class="text-right">Paid</th>
                <th class="text-right">Balance</th><th>Status</th></tr></thead>
            <tbody>
                @forelse ($rows as $i)
                    @php $cs = $i->customerScheme; @endphp
                    <tr>
                        <td class="font-mono text-xs">{{ $cs->customer->reg_no ?? '' }}</td>
                        <td><a href="{{ route('admin.customers.show', $cs->customer_id) }}" class="font-medium hover:text-brand-600 hover:underline">{{ $cs->customer->name ?? '—' }}</a></td>
                        <td>{{ $cs->customer->phone ?? '' }}</td>
                        <td class="text-xs">{{ $cs->scheme->name ?? '' }}<div class="font-mono text-ink-500">@if ($cs->book_no)Book {{ $cs->book_no }} · @endif{{ $cs->account_no }}</div></td>
                        <td>{{ $i->installment_no }}</td>
                        <td class="whitespace-nowrap {{ $i->due_date->isPast() ? 'font-semibold text-rose-600 dark:text-rose-400' : '' }}">{{ $i->due_date->format('d M Y') }}</td>
                        <td class="num">{{ money_in($i->amount) }}</td>
                        <td class="num text-ink-500">{{ money_in($i->paid_amount) }}</td>
                        <td class="num font-bold">{{ money_in($i->amount - $i->paid_amount) }}</td>
                        <td><x-status :value="$i->status" /></td>
                    </tr>
                @empty
                    <x-empty cols="10" message="Nothing pending 🎉" />
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($rows->hasPages())<div class="border-t border-ink-200 px-5 py-3 dark:border-ink-800">{{ $rows->links() }}</div>@endif
</div>
@endsection
