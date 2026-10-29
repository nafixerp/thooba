@extends('layouts.app')
@section('title', 'Agents')
@section('subtitle', 'Collection agents and their performance')

@section('content')

<x-filters :action="route('admin.agents.index')">
    <div class="min-w-[240px] flex-1">
        <label class="label">Search</label>
        <input name="q" value="{{ request('q') }}" class="input" placeholder="Name, code or phone">
    </div>
    <button class="btn-primary">Filter</button>
    <a href="{{ route('admin.agents.create') }}" class="btn-dark">+ New Agent</a>
</x-filters>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Code</th><th>Agent</th><th>Phone</th><th class="text-right">Customers</th>
                    <th class="text-right">Collected</th><th class="text-right">Comm %</th><th>Ledger</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($agents as $a)
                    <tr>
                        <td class="font-mono text-xs font-semibold">{{ $a->agent_code }}</td>
                        <td>
                            <a href="{{ route('admin.agents.show', $a) }}" class="font-medium hover:text-brand-600 hover:underline">{{ $a->name }}</a>
                            <div class="text-xs text-ink-500">{{ $a->email }}</div>
                        </td>
                        <td class="whitespace-nowrap">{{ $a->phone }}</td>
                        <td class="num">{{ $a->customers_count }}</td>
                        <td class="num font-semibold">{{ money_in($a->collected ?? 0) }}</td>
                        <td class="num">{{ number_format($a->commission_percent, 2) }}</td>
                        <td class="font-mono text-xs text-ink-500">{{ $a->accode ?: '—' }}</td>
                        <td><x-status :value="$a->status ? 'ACTIVE' : 'CLOSED'" /></td>
                        <td class="whitespace-nowrap text-right">
                            <a href="{{ route('admin.agents.show', $a) }}" class="btn-outline btn-sm">View</a>
                            <a href="{{ route('admin.agents.edit', $a) }}" class="btn-ghost btn-sm">Edit</a>
                        </td>
                    </tr>
                @empty
                    <x-empty cols="9" message="No agents yet" />
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($agents->hasPages())<div class="border-t border-ink-200 px-5 py-3 dark:border-ink-800">{{ $agents->links() }}</div>@endif
</div>
@endsection
