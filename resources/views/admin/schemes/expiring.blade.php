@extends('layouts.app')
@section('title', 'Expiring Installments')
@section('subtitle', 'Installments due soon or already overdue')

@section('content')
<x-filters :action="route('admin.schemes.expiring')">
    <div class="min-w-[150px]">
        <label class="label">Next</label>
        <select name="days" class="input" onchange="this.form.submit()">
            @foreach ([3, 7, 15, 30, 60] as $d)
                <option value="{{ $d }}" @selected($days == $d)>{{ $d }} days</option>
            @endforeach
        </select>
    </div>
    <button class="btn-primary">Apply</button>
</x-filters>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Customer</th><th>Phone</th><th>Scheme A/c</th><th>Installment</th>
                <th>Due Date</th><th class="text-right">Amount</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($rows as $i)
                    @php $cs = $i->customerScheme; @endphp
                    <tr>
                        <td>
                            <a href="{{ route('admin.customers.show', $cs->customer_id) }}" class="font-medium hover:text-brand-600 hover:underline">
                                {{ $cs->customer->name ?? '—' }}
                            </a>
                        </td>
                        <td>{{ $cs->customer->phone ?? '' }}</td>
                        <td class="font-mono text-xs">{{ $cs->account_no }}</td>
                        <td>#{{ $i->installment_no }}</td>
                        <td class="whitespace-nowrap {{ $i->due_date->isPast() ? 'font-semibold text-rose-600 dark:text-rose-400' : '' }}">
                            {{ $i->due_date->format('d M Y') }}
                        </td>
                        <td class="num font-semibold">{{ money_in($i->amount) }}</td>
                        <td><x-status :value="$i->status" /></td>
                        <td class="text-right">
                            <a href="{{ route('admin.payments.create', ['customer_id' => $cs->customer_id]) }}" class="btn-primary btn-sm">Collect</a>
                        </td>
                    </tr>
                @empty
                    <x-empty cols="8" message="Nothing due in this window" />
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
