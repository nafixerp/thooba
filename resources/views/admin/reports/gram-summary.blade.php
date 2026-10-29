@extends('layouts.app')
@section('title', 'Gram Summary')
@section('subtitle', 'Gold accumulated per customer')

@section('content')
<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Reg No</th><th>Customer</th><th>Phone</th><th class="text-right">Payments</th>
                <th class="text-right">Amount</th><th class="text-right">GST</th>
                <th class="text-right">Gram</th><th class="text-right">Current Value</th></tr></thead>
            <tbody>
                @php $rate = \App\Models\MetalRate::latest_rate(); @endphp
                @forelse ($rows as $c)
                    <tr>
                        <td class="font-mono text-xs">{{ $c->reg_no }}</td>
                        <td><a href="{{ route('admin.customers.show', $c) }}" class="font-medium hover:text-brand-600 hover:underline">{{ $c->name }}</a></td>
                        <td>{{ $c->phone }}</td>
                        <td class="num">{{ $c->payment_count }}</td>
                        <td class="num">{{ money_in($c->total_amount) }}</td>
                        <td class="num text-ink-500">{{ money_in($c->total_tax) }}</td>
                        <td class="num font-semibold">{{ number_format($c->gram_sum ?? 0, 3) }}</td>
                        <td class="num font-semibold text-brand-600">{{ money_in(($c->gram_sum ?? 0) * $rate) }}</td>
                    </tr>
                @empty
                    <x-empty cols="8" message="No data" />
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($rows->hasPages())<div class="border-t border-ink-200 px-5 py-3 dark:border-ink-800">{{ $rows->links() }}</div>@endif
</div>
@endsection
