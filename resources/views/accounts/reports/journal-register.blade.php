@extends('layouts.app')
@section('title', 'Journal Register')
@section('subtitle', 'Vouchers as they were entered, each with both sides together')

@section('content')

<x-filters :action="route('accounts.reports.journal-register')">
    <div>
        <label class="label">From</label>
        <input type="date" name="from" value="{{ $from }}" class="input">
    </div>
    <div>
        <label class="label">To</label>
        <input type="date" name="to" value="{{ $to }}" class="input">
    </div>
    <div class="min-w-[180px]">
        <label class="label">Voucher type</label>
        <select name="voucher_type" class="input">
            <option value="">All types</option>
            @foreach ($types as $k => $v)
                <option value="{{ $k }}" @selected($data['voucher_type'] === $k)>{{ $v }}</option>
            @endforeach
        </select>
    </div>
    <button class="btn-primary">Apply</button>
    <button type="button" onclick="window.print()" class="btn-outline">Print</button>
</x-filters>

<div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
    <x-stat label="Vouchers" :value="number_format(count($data['rows']))" icon="layers" tone="slate" />
    <x-stat label="Total value" :value="cur($data['total'])" icon="coins" tone="brand" />
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th class="w-28">Date</th>
                    <th>Voucher</th>
                    <th>Particulars</th>
                    <th class="text-right">Debit</th>
                    <th class="text-right">Credit</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($data['rows'] as $v)
                    @foreach ($v['lines'] as $i => $l)
                        <tr class="{{ $i === 0 ? 'border-t-2 border-ink-200 dark:border-ink-800' : '' }}">
                            <td class="whitespace-nowrap align-top">
                                {{ $i === 0 ? $v['date'] : '' }}
                            </td>
                            <td class="align-top">
                                @if ($i === 0)
                                    <a href="{{ route('accounts.vouchers.show', $v['sno']) }}" class="font-medium hover:text-brand-600">
                                        {{ $v['voucher_type'] }} {{ $v['voucher_no'] }}
                                    </a>
                                    @if ($v['narration'])
                                        <p class="text-[11.5px] text-ink-500">{{ $v['narration'] }}</p>
                                    @endif
                                @endif
                            </td>
                            <td class="{{ $l['credit'] > 0 ? 'pl-10' : '' }}">
                                <span class="text-ink-500">{{ $l['debit'] > 0 ? 'Dr' : 'Cr' }}</span>
                                {{ $l['name'] }}
                                @if ($l['remarks'])
                                    <span class="text-[11.5px] text-ink-500">· {{ $l['remarks'] }}</span>
                                @endif
                            </td>
                            <td class="num text-right">{{ $l['debit'] > 0 ? money_in($l['debit']) : '' }}</td>
                            <td class="num text-right">{{ $l['credit'] > 0 ? money_in($l['credit']) : '' }}</td>
                        </tr>
                    @endforeach
                @empty
                    <x-empty :cols="5" message="No voucher in this period" />
                @endforelse
            </tbody>
            @if (count($data['rows']))
                <tfoot>
                    <tr class="border-t-2 border-ink-300 bg-ink-50 font-bold dark:border-ink-700 dark:bg-ink-950/60">
                        <td colspan="3">Total</td>
                        <td class="num text-right">{{ money_in($data['total']) }}</td>
                        <td class="num text-right">{{ money_in($data['total']) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

@endsection
