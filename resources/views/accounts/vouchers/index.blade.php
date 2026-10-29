@extends('layouts.app')
@section('title', 'Vouchers')
@section('subtitle', 'daybookpart headers with their daybook lines')

@section('content')

<x-filters :action="route('accounts.vouchers.index')">
    <div><label class="label">From</label><input type="date" name="from" value="{{ $from }}" class="input"></div>
    <div><label class="label">To</label><input type="date" name="to" value="{{ $to }}" class="input"></div>
    <div class="min-w-[170px]">
        <label class="label">Type</label>
        <select name="voucher_type" class="input">
            <option value="">All types</option>
            @foreach ($types as $k => $v)
                <option value="{{ $k }}" @selected(request('voucher_type') === $k)>{{ $v }}</option>
            @endforeach
        </select>
    </div>
    <div class="min-w-[150px]">
        <label class="label">Status</label>
        <select name="status" class="input">
            <option value="">All</option>
            @foreach (['POSTED','DRAFT','CANCELLED'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
            @endforeach
        </select>
    </div>
    <div class="min-w-[180px] flex-1">
        <label class="label">Search</label>
        <input name="q" value="{{ request('q') }}" class="input" placeholder="Voucher no / narration">
    </div>
    <button class="btn-primary">Filter</button>
    <a href="{{ route('accounts.vouchers.create') }}" class="btn-dark">+ New Voucher</a>
</x-filters>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Sno</th><th>Date</th><th>Type</th><th>Voucher No</th>
                    <th>Particulars</th><th class="text-right">Amount</th><th>Status</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($vouchers as $v)
                    <tr class="{{ $v->status === 'CANCELLED' ? 'opacity-50' : '' }}">
                        <td class="font-mono text-xs text-ink-500">{{ $v->sno }}</td>
                        <td class="whitespace-nowrap">{{ $v->voucher_date->format('d M Y') }}</td>
                        <td><span class="badge-gray">{{ $v->voucher_type }}</span></td>
                        <td>
                            <a href="{{ route('accounts.vouchers.show', $v->sno) }}"
                               class="font-mono text-[12.5px] font-medium text-brand-600 hover:underline">{{ $v->voucher_no }}</a>
                        </td>
                        <td class="max-w-[380px]">
                            <div class="truncate text-[13px]">{{ $v->narration ?: '—' }}</div>
                            <div class="truncate text-xs text-ink-500">
                                {{ $v->lines->take(3)->map(fn ($l) => ($l->drcr === 'D' ? 'Dr ' : 'Cr ') . ($l->account->name ?? $l->accode))->join(' / ') }}
                                {{ $v->lines->count() > 3 ? ' …' : '' }}
                            </div>
                        </td>
                        <td class="num font-semibold">{{ money_in($v->total_amount) }}</td>
                        <td><x-status :value="$v->status" /></td>
                        <td class="whitespace-nowrap text-right">
                            <a href="{{ route('accounts.vouchers.show', $v->sno) }}" class="btn-outline btn-sm">View</a>
                        </td>
                    </tr>
                @empty
                    <x-empty cols="8" message="No vouchers in this period" />
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($vouchers->hasPages())
        <div class="border-t border-ink-200 px-5 py-3 dark:border-ink-800">{{ $vouchers->links() }}</div>
    @endif
</div>

@endsection
