@extends('layouts.app')
@section('title', 'Customer Points')

@section('content')
<x-filters :action="route('admin.points.index')">
    <div class="min-w-[240px] flex-1"><label class="label">Search</label><input name="q" value="{{ request('q') }}" class="input" placeholder="Customer name or phone"></div>
    <button class="btn-primary">Filter</button>
</x-filters>

<div class="grid grid-cols-1 gap-5 lg:grid-cols-4">
    <div class="card lg:col-span-3">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Customer</th><th>Phone</th><th>Referral ID</th>
                    <th class="text-right">Balance</th><th class="text-right">Redeemed</th><th class="text-right">Total</th></tr></thead>
                <tbody>
                    @forelse ($points as $p)
                        <tr>
                            <td><a href="{{ route('admin.customers.show', $p->customer_id) }}" class="font-medium hover:text-brand-600 hover:underline">{{ $p->customer->name ?? '—' }}</a></td>
                            <td>{{ $p->customer->phone ?? '' }}</td>
                            <td class="font-mono text-xs">{{ $p->referral_id ?: $p->customer->referral_code ?: '—' }}</td>
                            <td class="num font-semibold text-emerald-600 dark:text-emerald-400">{{ number_format($p->balance_point, 0) }}</td>
                            <td class="num text-ink-500">{{ number_format($p->redeemed_point, 0) }}</td>
                            <td class="num font-semibold">{{ number_format($p->total_point, 0) }}</td>
                        </tr>
                    @empty
                        <x-empty cols="6" message="No point records yet" />
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($points->hasPages())<div class="border-t border-ink-200 px-5 py-3 dark:border-ink-800">{{ $points->links() }}</div>@endif
    </div>

    <div class="space-y-5">
        <div class="card">
            <div class="card-head"><h3 class="card-title">Add Privilege Points</h3></div>
            <form method="POST" action="{{ route('admin.points.add') }}" class="card-pad space-y-3">
                @csrf
                <div>
                    <label class="label">Customer</label>
                    <select name="customer_id" class="input" required>
                        @foreach (\App\Models\Customer::active()->orderBy('name')->get(['id','name','phone']) as $c)
                            <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->phone }})</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="label">Points</label><input type="number" step="0.01" name="points" class="input" required></div>
                <div><label class="label">Note</label><input name="note" class="input"></div>
                <button class="btn-primary w-full">Add Privilege Points</button>
            </form>
        </div>

        <div class="card">
            <div class="card-head"><h3 class="card-title">Redeem Points</h3></div>
            <form method="POST" action="{{ route('admin.points.redeem') }}" class="card-pad space-y-3">
                @csrf
                <div>
                    <label class="label">Customer</label>
                    <select name="customer_id" class="input" required>
                        @foreach (\App\Models\Customer::active()->orderBy('name')->get(['id','name','phone']) as $c)
                            <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->phone }})</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="label">Points</label><input type="number" step="0.01" name="points" class="input" required></div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="label">Amount</label><input type="number" step="0.01" name="amount" class="input"></div>
                    <div><label class="label">Gram</label><input type="number" step="0.001" name="gram" class="input"></div>
                </div>
                <div><label class="label">Note</label><input name="note" class="input"></div>
                <button class="btn-dark w-full">Redeem</button>
            </form>
        </div>
    </div>
</div>
@endsection
