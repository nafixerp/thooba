@extends('layouts.app')
@section('title', 'Matured Schemes')
@section('subtitle', 'Ready for redemption')

@section('content')
<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Account No</th>
                    <th>Customer</th>
                    <th>Phone</th>
                    <th>Scheme</th>
                    <th class="text-right">Installments</th>
                    <th class="text-right">Total Paid</th>
                    <th class="text-right">Gram</th>
                    <th>Maturity</th>
                    <th>Notification</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $r)
                    @php
                        $digits = preg_replace('/\D+/', '', $r->customer->phone ?? '');
                        $waPhone = strlen($digits) === 10 ? '91'.$digits : $digits;
                        $waMessage = rawurlencode('Hi '.$r->customer->name.', your scheme '.$r->account_no.' has matured. Please contact us for redemption/refund.');
                    @endphp
                    <tr>
                        <td class="font-mono text-xs font-semibold">{{ $r->account_no }}</td>
                        <td><a href="{{ route('admin.customers.show', $r->customer_id) }}" class="font-medium hover:text-brand-600 hover:underline">{{ $r->customer->name ?? '-' }}</a></td>
                        <td>{{ $r->customer->phone ?? '' }}</td>
                        <td>{{ $r->scheme->name ?? '-' }}</td>
                        <td class="num">{{ $r->paid_installments }}/{{ $r->total_installments }}</td>
                        <td class="num font-semibold">{{ money_in($r->total_paid) }}</td>
                        <td class="num">{{ number_format($r->total_gram, 3) }}</td>
                        <td class="whitespace-nowrap">{{ $r->maturity_date?->format('d M Y') ?: '-' }}</td>
                        <td class="whitespace-nowrap">
                            @if ($r->maturity_notified_at)
                                <span class="badge-green">{{ $r->maturity_notified_at->format('d M Y') }}</span>
                            @else
                                <form method="POST" action="{{ route('admin.schemes.notify-maturity', $r) }}">
                                    @csrf
                                    <input type="hidden" name="note" value="Customer notified from matured schemes list.">
                                    <button class="btn-outline btn-sm">Notify</button>
                                </form>
                            @endif
                        </td>
                        <td><x-status :value="$r->status" /></td>
                        <td class="text-right">
                            <div class="flex justify-end gap-2">
                                @if ($waPhone)
                                    <a href="https://wa.me/{{ $waPhone }}?text={{ $waMessage }}" target="_blank" class="btn-outline btn-sm">WhatsApp</a>
                                @endif
                                <a href="{{ route('admin.schemes.enrollment', $r) }}#scheme-closure" class="btn-primary btn-sm">Finish / Close</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-empty cols="11" message="No matured schemes" />
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($rows->hasPages())<div class="border-t border-ink-200 px-5 py-3 dark:border-ink-800">{{ $rows->links() }}</div>@endif
</div>
@endsection
