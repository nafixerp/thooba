@extends('layouts.app')
@section('title', 'Day Book')
@section('subtitle', 'Every ledger entry posted, both sides')

@section('content')

<x-filters :action="route('accounts.reports.daybook')">
    <div>
        <label class="label">From</label>
        <input type="date" name="from" value="{{ $from }}" class="input">
    </div>
    <div>
        <label class="label">To</label>
        <input type="date" name="to" value="{{ $to }}" class="input">
    </div>
    <div class="min-w-[180px]">
        <label class="label">Voucher Type</label>
        <select name="voucher_type" class="input">
            <option value="">All types</option>
            @foreach ($types as $k => $v)
                <option value="{{ $k }}" @selected(request('voucher_type') === $k)>{{ $v }}</option>
            @endforeach
        </select>
    </div>
    <button class="btn-primary">Apply</button>
    <button type="button" onclick="window.print()" class="btn-outline">Print</button>
</x-filters>

@php
    $totalDr = $rows->sum('dr_amount');
    $totalCr = $rows->sum('cr_amount');
@endphp

<div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
    <x-stat label="Entries" :value="number_format($rows->count())" icon="layers" tone="slate" />
    <x-stat label="Total Debit" :value="cur($totalDr)" icon="trend" tone="blue" />
    <x-stat label="Total Credit" :value="cur($totalCr)" icon="trenddown" tone="violet" />
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Voucher</th>
                    <th>Ledger (accode)</th>
                    <th>Opposite (opaccode)</th>
                    <th>Remarks</th>
                    <th class="text-right">Debit</th>
                    <th class="text-right">Credit</th>
                </tr>
            </thead>
            <tbody>
                @php $lastSno = null; @endphp
                @forelse ($rows as $r)
                    @if ($r->sno !== $lastSno)
                        <tr class="bg-ink-100/60 dark:bg-ink-800/50">
                            <td colspan="7" class="px-4 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-ink-500">
                                {{ $r->voucher_date->format('d M Y') }} · {{ $r->voucher_type }} · {{ $r->voucher_no }}
                            </td>
                        </tr>
                        @php $lastSno = $r->sno; @endphp
                    @endif
                    <tr>
                        <td class="whitespace-nowrap text-xs text-ink-500">{{ $r->voucher_date->format('d-m-Y') }}</td>
                        <td>
                            <a href="{{ route('accounts.vouchers.show', $r->sno) }}" class="font-mono text-xs text-brand-600 hover:underline">
                                {{ $r->voucher_no }}
                            </a>
                        </td>
                        <td>
                            <span class="font-mono text-xs text-ink-500">{{ $r->accode }}</span>
                            <span class="ml-1 font-medium">{{ $r->account->name ?? '' }}</span>
                        </td>
                        <td class="text-ink-600 dark:text-ink-400">{{ $r->oppositeAccount->name ?? '—' }}</td>
                        <td class="max-w-[240px] truncate text-xs text-ink-500">{{ $r->remarks }}</td>
                        <td class="num">{{ $r->dr_amount > 0 ? money_in($r->dr_amount) : '' }}</td>
                        <td class="num">{{ $r->cr_amount > 0 ? money_in($r->cr_amount) : '' }}</td>
                    </tr>
                @empty
                    <x-empty cols="7" message="No entries in this period" />
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="text-right">TOTAL</td>
                    <td class="num">{{ money_in($totalDr) }}</td>
                    <td class="num">{{ money_in($totalCr) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

@endsection
