@extends('layouts.app')
@section('title', 'Customers')
@section('subtitle', 'All registered scheme customers')

@section('content')

<x-filters :action="route('admin.customers.index')">
    <div class="min-w-[220px] flex-1">
        <label class="label">Search</label>
        <input name="q" value="{{ request('q') }}" class="input" placeholder="Name, reg no, phone or email">
    </div>
    <div class="min-w-[180px]">
        <label class="label">Agent</label>
        <select name="agent_id" class="input">
            <option value="">All agents</option>
            @foreach ($agents as $a)
                <option value="{{ $a->id }}" @selected(request('agent_id') == $a->id)>{{ $a->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="min-w-[140px]">
        <label class="label">Status</label>
        <select name="status" class="input">
            <option value="">All</option>
            <option value="1" @selected(request('status') === '1')>Active</option>
            <option value="0" @selected(request('status') === '0')>Inactive</option>
        </select>
    </div>
    <button class="btn-primary">Filter</button>
    <a href="{{ route('admin.customers.create') }}" class="btn-dark">+ New Customer</a>
</x-filters>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Reg No</th><th>Customer</th><th>Phone</th><th>Agent</th>
                    <th class="text-right">Schemes</th><th class="text-right">Wallet</th>
                    <th class="text-right">Gram</th><th>Status</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($customers as $c)
                    <tr>
                        <td class="font-mono text-xs font-semibold">{{ $c->reg_no }}</td>
                        <td>
                            <div class="flex items-center gap-2.5">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-300 to-brand-600 text-[11px] font-bold text-white">
                                    {{ strtoupper(substr($c->name, 0, 2)) }}
                                </span>
                                <div class="min-w-0">
                                    <a href="{{ route('admin.customers.show', $c) }}" class="block truncate font-medium hover:text-brand-600 hover:underline">{{ $c->name }}</a>
                                    <div class="truncate text-xs text-ink-500">{{ $c->email ?: $c->district?->name }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="whitespace-nowrap">{{ $c->phone }}</td>
                        <td class="text-ink-600 dark:text-ink-400">{{ $c->agent?->name ?: '—' }}</td>
                        <td class="num">{{ $c->active_schemes }}</td>
                        <td class="num">{{ money_in($c->wallet_amount) }}</td>
                        <td class="num">{{ number_format($c->total_gram, 3) }}</td>
                        <td><x-status :value="$c->status ? 'ACTIVE' : 'CLOSED'" /></td>
                        <td class="whitespace-nowrap text-right">
                            <a href="{{ route('admin.customers.show', $c) }}" class="btn-outline btn-sm">View</a>
                            <a href="{{ route('admin.customers.edit', $c) }}" class="btn-ghost btn-sm">Edit</a>
                        </td>
                    </tr>
                @empty
                    <x-empty cols="9" message="No customers found">
                        <a href="{{ route('admin.customers.create') }}" class="btn-primary btn-sm mt-3">Add the first customer</a>
                    </x-empty>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($customers->hasPages())
        <div class="border-t border-ink-200 px-5 py-3 dark:border-ink-800">{{ $customers->links() }}</div>
    @endif
</div>

@endsection
