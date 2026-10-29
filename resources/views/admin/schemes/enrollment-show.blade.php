@extends('layouts.app')
@section('title', 'Enrollment ' . $enrollment->account_no)
@section('subtitle', $enrollment->customer->name . ' - ' . $enrollment->scheme->name)

@section('content')
@php
    $digits = preg_replace('/\D+/', '', $enrollment->customer->phone ?? '');
    $waPhone = strlen($digits) === 10 ? '91'.$digits : $digits;
    $waMessage = rawurlencode('Hi '.$enrollment->customer->name.', your scheme '.$enrollment->account_no.' has matured. Please contact us for redemption/refund.');
@endphp

<div class="mb-5 flex flex-wrap gap-2 no-print">
    <a href="{{ route('admin.schemes.enrollments') }}" class="btn-outline">&larr; Back</a>
    <a href="{{ route('admin.payments.create', ['customer_id' => $enrollment->customer_id]) }}" class="btn-primary">Record Payment</a>
    @if (in_array($enrollment->status, ['ACTIVE', 'MATURED']))
        <a href="#scheme-closure" class="btn-dark">Finish / Close Scheme</a>
        @if ($waPhone)
            <a href="https://wa.me/{{ $waPhone }}?text={{ $waMessage }}" target="_blank" class="btn-outline">WhatsApp</a>
        @endif
        <form method="POST" action="{{ route('admin.schemes.notify-maturity', $enrollment) }}" class="flex flex-wrap gap-2">
            @csrf
            <input name="note" class="input h-10 w-72" placeholder="Notification note" value="{{ $enrollment->maturity_notification_note }}">
            <button class="btn-outline">{{ $enrollment->maturity_notified_at ? 'Update Notification' : 'Mark Notified' }}</button>
        </form>
    @endif
</div>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
    <x-stat label="Total Paid" :value="cur($enrollment->total_paid)" icon="wallet" tone="brand" />
    <x-stat label="Gram Accumulated" :value="number_format($enrollment->total_gram, 3) . ' g'" icon="gem" tone="amber" />
    <x-stat label="Installments" :value="$enrollment->paid_installments . ' / ' . $enrollment->total_installments"
            :sub="$enrollment->progress . '% complete'" icon="layers" tone="violet" />
    <x-stat label="GST Collected" :value="cur($enrollment->total_gst)" icon="coins" tone="blue" />
    <x-stat label="Maturity Notified" :value="$enrollment->maturity_notified_at ? $enrollment->maturity_notified_at->format('d M Y') : 'Pending'" icon="check" tone="green" />
</div>

@if (in_array($enrollment->status, ['ACTIVE', 'MATURED']))
    <div class="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-3">
        <form id="scheme-closure" method="POST" action="{{ route('admin.schemes.redeem', $enrollment) }}"
              class="card xl:col-span-2"
              onsubmit="return confirm('Close this scheme and settle the selected balance?')">
            @csrf
            <div class="card-head">
                <h3 class="card-title">Scheme Closure</h3>
                <span class="badge-blue">Balance settlement</span>
            </div>
            <div class="card-pad grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label class="label">Settlement Type</label>
                    <select name="closure_type" class="input">
                        <option value="GOLD_REDEEM">Gold redeem / sales</option>
                        <option value="REFUND">Cash / bank refund</option>
                        <option value="NO_PAYOUT">Close without payout</option>
                    </select>
                </div>
                <div>
                    <label class="label">Amount</label>
                    <input type="number" step="0.01" min="0" name="amount" class="input"
                           value="{{ number_format((float) $enrollment->total_paid + (float) $enrollment->bonus_amount, 2, '.', '') }}">
                </div>
                <div>
                    <label class="label">Refund Mode</label>
                    <select name="payment_mode" class="input">
                        @foreach (['CASH', 'UPI', 'BANK', 'CARD', 'CHEQUE'] as $mode)
                            <option value="{{ $mode }}">{{ $mode }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Close Date</label>
                    <input type="date" name="date" class="input" value="{{ now()->toDateString() }}">
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Note</label>
                    <input name="note" class="input" placeholder="Closure remarks">
                </div>
                <label class="flex items-center gap-2 rounded-lg border border-ink-200 px-3 py-2 text-sm dark:border-ink-800">
                    <input type="checkbox" name="create_new" value="1" class="rounded border-ink-300">
                    <span>Create new scheme after close</span>
                </label>
                <div>
                    <label class="label">New Start Date</label>
                    <input type="date" name="new_start_date" class="input" value="{{ now()->toDateString() }}">
                </div>
            </div>
            <div class="border-t border-ink-200 px-5 py-4 text-right dark:border-ink-800">
                <button class="btn-dark">Finish & Close</button>
            </div>
        </form>

        <form method="POST" action="{{ route('admin.schemes.block', $enrollment) }}"
              class="card h-fit"
              onsubmit="return confirm('Block/cancel this scheme without balance settlement?')">
            @csrf
            <div class="card-head">
                <h3 class="card-title">Block Scheme</h3>
                <span class="badge-rose">No payout</span>
            </div>
            <div class="card-pad space-y-3">
                <div>
                    <label class="label">Block Date</label>
                    <input type="date" name="date" class="input" value="{{ now()->toDateString() }}">
                </div>
                <div>
                    <label class="label">Reason</label>
                    <textarea name="note" class="input min-h-24" placeholder="Reason for blocking"></textarea>
                </div>
                <button class="btn-danger w-full">Block / Cancel</button>
            </div>
        </form>
    </div>
@elseif ($enrollment->status === 'CLOSED')
    <div class="mt-5 card">
        <div class="card-head">
            <h3 class="card-title">Closure Details</h3>
            <x-status :value="$enrollment->status" />
        </div>
        <div class="card-pad grid grid-cols-1 gap-4 text-sm sm:grid-cols-4">
            <div><span class="text-ink-500">Type</span><div class="font-semibold">{{ $enrollment->closure_type ?: '-' }}</div></div>
            <div><span class="text-ink-500">Amount</span><div class="font-semibold">{{ money_in($enrollment->closure_amount) }}</div></div>
            <div><span class="text-ink-500">Mode</span><div class="font-semibold">{{ $enrollment->closure_mode ?: '-' }}</div></div>
            <div><span class="text-ink-500">Voucher</span><div class="font-semibold">{{ $enrollment->closure_daybook_sno ?: '-' }}</div></div>
        </div>
    </div>
@endif

<div class="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-2">
    <div class="card">
        <div class="card-head"><h3 class="card-title">Installment Schedule</h3><x-status :value="$enrollment->status" /></div>
        <div class="table-wrap" style="max-height:520px;overflow-y:auto">
            <table class="table">
                <thead><tr><th>#</th><th>Due Date</th><th class="text-right">Amount</th>
                    <th class="text-right">Paid</th><th class="text-right">Gram</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach ($enrollment->installments as $i)
                        <tr>
                            <td class="text-xs text-ink-500">{{ $i->installment_no }}</td>
                            <td class="whitespace-nowrap">{{ $i->due_date->format('d M Y') }}</td>
                            <td class="num">{{ money_in($i->amount) }}</td>
                            <td class="num font-semibold">{{ $i->paid_amount > 0 ? money_in($i->paid_amount) : '-' }}</td>
                            <td class="num">{{ $i->gram > 0 ? number_format($i->gram, 3) : '-' }}</td>
                            <td><x-status :value="$i->status" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h3 class="card-title">Payments</h3></div>
        <div class="table-wrap" style="max-height:520px;overflow-y:auto">
            <table class="table">
                <thead><tr><th>Receipt</th><th>Date</th><th>Mode</th><th class="text-right">Total</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($enrollment->payments as $p)
                        <tr>
                            <td><a href="{{ route('admin.payments.show', $p) }}" class="font-mono text-xs text-brand-600 hover:underline">{{ $p->receipt_no }}</a></td>
                            <td class="whitespace-nowrap">{{ $p->payment_date->format('d M Y') }}</td>
                            <td><span class="badge-gray">{{ $p->payment_mode }}</span></td>
                            <td class="num font-semibold">{{ money_in($p->total) }}</td>
                            <td><x-status :value="$p->status" /></td>
                        </tr>
                    @empty
                        <x-empty cols="5" message="No payments yet" />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
