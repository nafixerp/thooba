@extends('layouts.app')
@section('title', 'Bank Reconciliation')
@section('subtitle', 'Check direct UPI collections against what the bank actually received')

@section('content')

{{-- Why this screen exists --}}
<div class="card card-pad mb-5 border-l-4 border-l-amber-400">
    <p class="text-sm leading-relaxed">
        A direct UPI payment has no gateway behind it, so the app's word that the money arrived
        is the customer's device talking &mdash; it proves nothing. Upload the bank statement and
        every credit is matched against what customers have claimed. A payment reaches the
        accounts only when you approve it here.
    </p>
</div>

{{-- ---------- import ---------- --}}
<div class="card mb-5">
    <div class="card-head"><h3 class="card-title">Upload a statement</h3></div>
    <form method="POST" action="{{ route('accounts.reconciliation.import') }}"
          enctype="multipart/form-data" class="card-pad flex flex-wrap items-end gap-3">
        @csrf
        <div class="min-w-[260px] flex-1">
            <label class="label">Statement CSV</label>
            <input type="file" name="statement" accept=".csv,text/csv" class="input" required>
            <p class="form-hint">
                Export it from net banking as CSV. The header needs a date column and a
                deposit / credit column &mdash; the usual HDFC, SBI, ICICI and Axis exports work as they are.
                Re-uploading an overlapping range is safe; lines already held are skipped.
            </p>
        </div>
        <div class="min-w-[200px]">
            <label class="label">Bank</label>
            <select name="bank_id" class="input">
                <option value="">Not specified</option>
                @foreach ($banks as $b)
                    <option value="{{ $b->id }}">{{ $b->bank_name }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn-primary">Import &amp; match</button>
    </form>
</div>

{{-- ---------- counters + bulk action ---------- --}}
<div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
    <x-stat label="Unmatched credits" :value="number_format($counts['unmatched'])"
            sub="Money in the bank nobody has claimed" icon="alert"
            :tone="$counts['unmatched'] > 0 ? 'amber' : 'slate'" />
    <x-stat label="Matched" :value="number_format($counts['matched'])"
            sub="Ready to approve" icon="check" tone="green" />
    <x-stat label="Claimed, not in the bank" :value="number_format($unmatchedClaims->count())"
            sub="Customers say they paid" icon="trenddown"
            :tone="$unmatchedClaims->count() > 0 ? 'rose' : 'slate'" />
</div>

<x-filters :action="route('accounts.reconciliation.index')">
    <div class="min-w-[170px]">
        <label class="label">Show</label>
        <select name="status" class="input" onchange="this.form.submit()">
            @foreach (['UNMATCHED' => 'Unmatched', 'MATCHED' => 'Matched', 'IGNORED' => 'Set aside', 'ALL' => 'All'] as $k => $label)
                <option value="{{ $k }}" @selected($status === $k)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="label">From</label>
        <input type="date" name="from" value="{{ $from }}" class="input">
    </div>
    <div>
        <label class="label">To</label>
        <input type="date" name="to" value="{{ $to }}" class="input">
    </div>
    <button class="btn-primary">Apply</button>

    <button form="rematch-form" class="btn-outline">Re-run matching</button>
</x-filters>

<form id="rematch-form" method="POST" action="{{ route('accounts.reconciliation.rematch') }}" class="hidden">@csrf</form>

@if ($counts['matched'] > 0)
    <form method="POST" action="{{ route('accounts.reconciliation.approve-matched') }}" class="mb-5 no-print"
          onsubmit="return confirm('Approve every payment the bank quoted a reference for? This posts them to the accounts.')">
        @csrf
        <button class="btn-primary">Approve all reference matches</button>
        <span class="ml-2 text-xs text-ink-500">
            Only lines matched on our order number or the customer's UTR. An amount-and-date
            guess is left for you to check.
        </span>
    </form>
@endif

{{-- ---------- statement lines ---------- --}}
<div class="card mb-5">
    <div class="card-head">
        <h3 class="card-title">Statement credits</h3>
        <span class="badge-gray">{{ $lines->total() }}</span>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th class="w-28">Date</th>
                    <th>Narration</th>
                    <th class="text-right">Credit</th>
                    <th>Matched to</th>
                    <th class="w-64"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lines as $line)
                    <tr>
                        <td class="whitespace-nowrap">{{ $line->txn_date->format('d M Y') }}</td>
                        <td>
                            <p class="text-[12.5px] leading-snug">{{ $line->narration ?: '—' }}</p>
                            @if ($line->ref_no)
                                <p class="text-[11px] text-ink-500">Ref {{ $line->ref_no }}</p>
                            @endif
                        </td>
                        <td class="num text-right font-semibold">{{ money_in($line->credit) }}</td>
                        <td>
                            @if ($line->payment)
                                <p class="font-medium">{{ $line->payment->customer?->name }}</p>
                                <p class="text-[11px] text-ink-500">
                                    {{ $line->payment->receipt_no }} ·
                                    <span class="{{ $line->isStrongMatch() ? 'badge-green' : 'badge-amber' }}">
                                        {{ $line->match_type === 'ORDER_NO' ? 'Order no' : ($line->match_type === 'UTR' ? 'UTR' : ($line->match_type === 'MANUAL' ? 'By hand' : 'Amount + date')) }}
                                    </span>
                                    <span class="{{ $line->payment->status === 'APPROVED' ? 'badge-green' : 'badge-amber' }}">
                                        {{ $line->payment->status }}
                                    </span>
                                </p>
                            @elseif ($line->status === 'IGNORED')
                                <span class="badge-gray">Set aside</span>
                                @if ($line->note)
                                    <p class="text-[11px] text-ink-500">{{ $line->note }}</p>
                                @endif
                            @else
                                <span class="badge-rose">Nobody claimed this</span>
                            @endif
                        </td>
                        <td class="text-right">
                            <div class="flex flex-wrap justify-end gap-1.5">
                                @if ($line->payment && $line->payment->status === 'PENDING')
                                    <form method="POST" action="{{ route('accounts.reconciliation.approve', $line->id) }}"
                                          onsubmit="return confirm('Approve {{ $line->payment->receipt_no }}? This posts it to the accounts.')">
                                        @csrf
                                        <button class="btn-primary btn-sm">Approve</button>
                                    </form>
                                @endif

                                @if ($line->status === 'UNMATCHED' && $unmatchedClaims->isNotEmpty())
                                    <form method="POST" action="{{ route('accounts.reconciliation.link', $line->id) }}"
                                          class="flex gap-1.5">
                                        @csrf
                                        <select name="payment_id" class="input !py-1 !text-xs" required>
                                            <option value="">Link to…</option>
                                            @foreach ($unmatchedClaims as $claim)
                                                <option value="{{ $claim->id }}">
                                                    {{ $claim->customer?->name }} · {{ money_in($claim->total) }} · {{ $claim->payment_date->format('d M') }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button class="btn-outline btn-sm">Link</button>
                                    </form>
                                @endif

                                @if ($line->status === 'UNMATCHED')
                                    <form method="POST" action="{{ route('accounts.reconciliation.ignore', $line->id) }}">
                                        @csrf
                                        <button class="btn-outline btn-sm">Set aside</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('accounts.reconciliation.reset', $line->id) }}">
                                        @csrf
                                        <button class="btn-outline btn-sm">Reopen</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-empty :cols="5" message="No statement lines here. Upload a CSV to start." />
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($lines->hasPages())
        <div class="px-5 py-3">{{ $lines->links() }}</div>
    @endif
</div>

{{-- ---------- the other side: claims with no bank line ---------- --}}
@if ($unmatchedClaims->isNotEmpty())
    <div class="card">
        <div class="card-head">
            <div>
                <h3 class="card-title">Claimed, but not in the statement</h3>
                <p class="text-xs text-ink-500">
                    Customers who say they paid and no credit matches. Either the statement for
                    those days has not been uploaded, or the money never arrived.
                </p>
            </div>
            <span class="badge-rose">{{ $unmatchedClaims->count() }}</span>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Receipt</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Reference given</th>
                        <th class="text-right">Amount</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($unmatchedClaims as $claim)
                        <tr>
                            <td class="font-mono text-xs">{{ $claim->receipt_no }}</td>
                            <td class="font-medium">{{ $claim->customer?->name }}</td>
                            <td class="whitespace-nowrap">{{ $claim->payment_date->format('d M Y') }}</td>
                            <td class="text-ink-600 dark:text-ink-400">{{ $claim->txn_ref ?: '—' }}</td>
                            <td class="num text-right font-semibold">{{ money_in($claim->total) }}</td>
                            <td class="text-right">
                                <a href="{{ route('admin.payments.show', $claim->id) }}" class="btn-outline btn-sm">Open</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@endsection
