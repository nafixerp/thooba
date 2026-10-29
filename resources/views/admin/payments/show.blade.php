@extends('layouts.app')
@section('title', 'Receipt ' . $payment->receipt_no)
@section('subtitle', $payment->customer->name . ' · ' . $payment->payment_date->format('d M Y'))

@section('content')

<div class="mb-5 flex flex-wrap gap-2 no-print">
    <a href="{{ route('admin.payments.index') }}" class="btn-outline">&larr; Back</a>
    <button onclick="window.print()" class="btn-outline">Print Receipt</button>
    @if ($payment->status === 'PENDING')
        <form method="POST" action="{{ route('admin.payments.approve', $payment) }}">
            @csrf<button class="btn-success">Approve &amp; Post</button>
        </form>
        <form method="POST" action="{{ route('admin.payments.reject', $payment) }}">
            @csrf<input type="hidden" name="reason" value="Rejected by admin">
            <button class="btn-danger">Reject</button>
        </form>
    @endif
</div>

<div class="grid grid-cols-1 gap-5 lg:grid-cols-3">

    <div class="card lg:col-span-2">
        <div class="card-head">
            <div>
                <h3 class="card-title">Payment Receipt</h3>
                <p class="text-xs text-ink-500">{{ \App\Models\Setting::get('company_name', 'Scheme ERP') }}</p>
            </div>
            <x-status :value="$payment->status" />
        </div>

        <div class="card-pad">
            <div class="grid grid-cols-2 gap-5 sm:grid-cols-4">
                @foreach ([
                    'Receipt No' => $payment->receipt_no,
                    'Date' => $payment->payment_date->format('d M Y'),
                    'Type' => $payment->payment_type,
                    'Mode' => $payment->payment_mode,
                ] as $k => $v)
                    <div>
                        <div class="text-[11px] font-semibold uppercase tracking-wide text-ink-500">{{ $k }}</div>
                        <div class="mt-0.5 text-sm font-medium">{{ $v }}</div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 rounded-xl border border-ink-200 dark:border-ink-800">
                <div class="divide-row">
                    @foreach ([
                        'Scheme Amount' => cur($payment->amount),
                        'GST / Tax' => cur($payment->tax),
                        'Gold Rate (per gram)' => cur($payment->rate),
                        'Gram Credited' => number_format($payment->gram, 3) . ' g',
                    ] as $k => $v)
                        <div class="flex justify-between px-4 py-2.5 text-sm">
                            <span class="text-ink-500">{{ $k }}</span>
                            <span class="font-mono font-medium">{{ $v }}</span>
                        </div>
                    @endforeach
                    <div class="flex justify-between bg-ink-50 px-4 py-3 dark:bg-ink-950/50">
                        <span class="font-semibold">Total Paid</span>
                        <span class="font-mono text-lg font-bold">{{ cur($payment->total) }}</span>
                    </div>
                </div>
            </div>

            @if ($payment->screenshot)
                <div class="mt-5">
                    <div class="label">Payment Proof</div>
                    <img src="{{ asset('storage/' . $payment->screenshot) }}" class="max-h-80 rounded-xl border border-ink-200 dark:border-ink-800" alt="">
                </div>
            @endif
        </div>
    </div>

    <div class="space-y-5">
        <div class="card">
            <div class="card-head"><h3 class="card-title">Customer</h3></div>
            <div class="card-pad space-y-2.5 text-sm">
                @foreach ([
                    'Name' => $payment->customer->name,
                    'Reg No' => $payment->customer->reg_no,
                    'Phone' => $payment->customer->phone,
                    'Scheme A/c' => $payment->customerScheme->account_no ?? '—',
                    'Scheme' => $payment->customerScheme->scheme->name ?? '—',
                    'Installment' => $payment->installment ? '#' . $payment->installment->installment_no : '—',
                    'Agent' => $payment->agent->name ?? '—',
                ] as $k => $v)
                    <div class="flex justify-between gap-3">
                        <span class="shrink-0 text-ink-500">{{ $k }}</span>
                        <span class="truncate text-right font-medium">{{ $v }}</span>
                    </div>
                @endforeach
                <a href="{{ route('admin.customers.show', $payment->customer) }}" class="btn-outline btn-sm mt-2 w-full">Open customer</a>
            </div>
        </div>

        @if ($payment->voucher)
            <div class="card">
                <div class="card-head">
                    <h3 class="card-title">Accounting Entry</h3>
                    <a href="{{ route('accounts.vouchers.show', $payment->voucher->sno) }}" class="text-xs font-medium text-brand-600 hover:underline">Open</a>
                </div>
                <div class="card-pad">
                    <div class="mb-3 text-xs text-ink-500">
                        {{ $payment->voucher->voucher_type }} · {{ $payment->voucher->voucher_no }}
                    </div>
                    <table class="w-full text-sm">
                        @foreach ($payment->voucher->lines as $l)
                            <tr class="border-b border-ink-100 last:border-0 dark:border-ink-800">
                                <td class="py-1.5">
                                    <span class="text-xs font-semibold {{ $l->drcr === 'D' ? 'text-sky-600' : 'text-violet-600' }}">
                                        {{ $l->drcr === 'D' ? 'Dr' : 'Cr' }}
                                    </span>
                                    <span class="ml-1">{{ $l->account->name ?? $l->accode }}</span>
                                </td>
                                <td class="py-1.5 text-right font-mono text-[13px]">{{ money_in(abs($l->amount)) }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </div>
        @else
            <div class="card card-pad text-sm text-ink-500">
                No accounting entry yet — approve this payment to post it to the daybook.
            </div>
        @endif
    </div>
</div>

@endsection
