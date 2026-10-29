@extends('layouts.app')
@section('title', 'Commission Payouts')
@section('subtitle', 'Each payout posts Dr Agent Commission · Cr Cash/Bank')

@section('content')
<div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <x-filters :action="route('admin.agents.payouts')">
            <div class="min-w-[200px]">
                <label class="label">Agent</label>
                <select name="agent_id" class="input">
                    <option value="">All agents</option>
                    @foreach ($agents as $a)<option value="{{ $a->id }}" @selected(request('agent_id') == $a->id)>{{ $a->name }}</option>@endforeach
                </select>
            </div>
            <div class="min-w-[150px]">
                <label class="label">Status</label>
                <select name="status" class="input">
                    <option value="">All</option>
                    <option value="PAID" @selected(request('status') === 'PAID')>Paid</option>
                    <option value="PENDING" @selected(request('status') === 'PENDING')>Pending</option>
                </select>
            </div>
            <button class="btn-primary">Filter</button>
        </x-filters>

        <div class="card">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Agent</th><th>Period</th><th class="text-right">Collected</th>
                        <th class="text-right">Commission</th><th>Received</th><th>Mode</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($payouts as $p)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.agents.show', $p->agent_id) }}" class="font-medium hover:text-brand-600 hover:underline">{{ $p->agent->name }}</a>
                                    <div class="font-mono text-xs text-ink-500">{{ $p->agent->agent_code }}</div>
                                </td>
                                <td class="whitespace-nowrap text-xs">{{ $p->from_date->format('d M y') }} — {{ $p->to_date->format('d M y') }}</td>
                                <td class="num text-ink-500">{{ money_in($p->collected_amount) }}</td>
                                <td class="num font-semibold">{{ money_in($p->amount) }}</td>
                                <td class="whitespace-nowrap">{{ $p->received_date?->format('d M Y') ?: '—' }}</td>
                                <td><span class="badge-gray">{{ $p->payment_mode }}</span></td>
                                <td><x-status :value="$p->status" /></td>
                                <td class="text-right">
                                    @if ($p->daybook_sno)
                                        <a href="{{ route('accounts.vouchers.show', $p->daybook_sno) }}" class="btn-outline btn-sm">Voucher</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-empty cols="8" message="No payouts yet" />
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($payouts->hasPages())<div class="border-t border-ink-200 px-5 py-3 dark:border-ink-800">{{ $payouts->links() }}</div>@endif
        </div>
    </div>

    <div class="card h-fit">
        <div class="card-head"><h3 class="card-title">Pay Commission</h3></div>
        <form method="POST" action="{{ route('admin.agents.payouts.store') }}" class="card-pad space-y-4">
            @csrf
            <div>
                <label class="label">Agent *</label>
                <select name="agent_id" class="input" required>
                    @foreach ($agents as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">From *</label><input type="date" name="from_date" class="input" required value="{{ now()->startOfMonth()->toDateString() }}"></div>
                <div><label class="label">To *</label><input type="date" name="to_date" class="input" required value="{{ now()->toDateString() }}"></div>
            </div>
            <div><label class="label">Amount *</label><input type="number" step="0.01" name="amount" class="input font-mono" required></div>
            <div><label class="label">Received Date</label><input type="date" name="received_date" class="input" value="{{ now()->toDateString() }}"></div>
            <div>
                <label class="label">Mode *</label>
                <select name="payment_mode" class="input" required>
                    @foreach (['BANK','CASH','UPI','CHEQUE'] as $m)<option value="{{ $m }}">{{ $m }}</option>@endforeach
                </select>
            </div>
            <div><label class="label">Notes</label><textarea name="notes" rows="2" class="input"></textarea></div>
            <button class="btn-primary w-full">Pay &amp; Post to Accounts</button>
        </form>
    </div>
</div>
@endsection
