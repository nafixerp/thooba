@extends('layouts.app')
@section('title', 'Voucher ' . $voucher->voucher_no)
@section('subtitle', $voucher->voucher_type . ' · ' . $voucher->voucher_date->format('d M Y'))

@section('content')

<div class="mb-5 flex flex-wrap gap-2 no-print">
    <a href="{{ route('accounts.vouchers.index') }}" class="btn-outline">&larr; Back</a>
    <button onclick="window.print()" class="btn-outline">Print</button>
    @if ($voucher->status === 'POSTED')
        <form method="POST" action="{{ route('accounts.vouchers.cancel', $voucher->sno) }}"
              onsubmit="return confirm('Cancel this voucher? It will be excluded from all reports.')">
            @csrf
            <input type="hidden" name="reason" value="Cancelled by user">
            <button class="btn-danger">Cancel Voucher</button>
        </form>
    @endif
</div>

<div class="card mx-auto max-w-4xl">
    <div class="card-head">
        <div>
            <h3 class="card-title">{{ config('scheme.voucher_types')[$voucher->voucher_type] ?? $voucher->voucher_type }} Voucher</h3>
            <p class="text-xs text-ink-500">{{ \App\Models\Setting::get('company_name', 'Scheme ERP') }}</p>
        </div>
        <x-status :value="$voucher->status" />
    </div>

    <div class="grid grid-cols-2 gap-4 border-b border-ink-200 px-5 py-4 sm:grid-cols-4 dark:border-ink-800">
        @foreach ([
            'Voucher No' => $voucher->voucher_no,
            'Date' => $voucher->voucher_date->format('d M Y'),
            'Financial Year' => $voucher->fy,
            'Reference' => $voucher->reference ?: '—',
        ] as $k => $v)
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wide text-ink-500">{{ $k }}</div>
                <div class="mt-0.5 text-sm font-medium">{{ $v }}</div>
            </div>
        @endforeach
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th><th>Ledger Account</th><th>Opposite</th><th>Remarks</th>
                    <th class="text-right">Debit</th><th class="text-right">Credit</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($voucher->lines as $l)
                    <tr>
                        <td class="text-xs text-ink-500">{{ $l->line_no }}</td>
                        <td>
                            <a href="{{ route('accounts.reports.general-ledger', ['accode' => $l->accode]) }}"
                               class="font-medium hover:text-brand-600 hover:underline">
                                {{ $l->account->name ?? $l->accode }}
                            </a>
                            <div class="font-mono text-xs text-ink-500">{{ $l->accode }}</div>
                        </td>
                        <td class="text-xs text-ink-500">{{ $l->oppositeAccount->name ?? '—' }}</td>
                        <td class="text-xs text-ink-500">{{ $l->remarks }}</td>
                        <td class="num">{{ $l->dr_amount > 0 ? money_in($l->dr_amount) : '' }}</td>
                        <td class="num">{{ $l->cr_amount > 0 ? money_in($l->cr_amount) : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-right">TOTAL</td>
                    <td class="num">{{ money_in($voucher->debit_total) }}</td>
                    <td class="num">{{ money_in($voucher->credit_total) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    @if ($voucher->narration)
        <div class="border-t border-ink-200 px-5 py-4 dark:border-ink-800">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-ink-500">Narration</div>
            <p class="mt-1 text-sm">{{ $voucher->narration }}</p>
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-ink-200 px-5 py-3 text-xs text-ink-500 dark:border-ink-800">
        <span>Source: {{ $voucher->source_type }}{{ $voucher->source_id ? ' #' . $voucher->source_id : '' }}</span>
        <span>
            Created by {{ $voucher->creator->name ?? 'system' }} on {{ $voucher->created_at->format('d M Y, h:i A') }}
        </span>
    </div>
</div>

@endsection
