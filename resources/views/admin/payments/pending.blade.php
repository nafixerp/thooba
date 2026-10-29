@extends('layouts.app')
@section('title', 'Pending Approvals')
@section('subtitle', 'Approve to post the double entry into the daybook')

@section('content')

<form method="POST" action="{{ route('admin.payments.bulk-approve') }}" x-data="{ sel: [] }">
    @csrf

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <span class="text-sm text-ink-500">
            <span x-text="sel.length"></span> selected
        </span>
        <button class="btn-success btn-sm" :disabled="!sel.length">Approve selected</button>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:40px"></th>
                        <th>Receipt</th><th>Customer</th><th>Scheme</th><th>Date</th><th>Mode</th>
                        <th>Txn Ref</th><th class="text-right">Total</th><th>Proof</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payments as $p)
                        <tr>
                            <td>
                                <input type="checkbox" name="ids[]" value="{{ $p->id }}" x-model="sel"
                                       class="h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500 dark:border-ink-700 dark:bg-ink-900">
                            </td>
                            <td class="font-mono text-xs">{{ $p->receipt_no }}</td>
                            <td>
                                <div class="font-medium">{{ $p->customer->name ?? '—' }}</div>
                                <div class="text-xs text-ink-500">{{ $p->customer->phone ?? '' }}</div>
                            </td>
                            <td class="font-mono text-xs text-ink-500">{{ $p->customerScheme->account_no ?? '—' }}</td>
                            <td class="whitespace-nowrap">{{ $p->payment_date->format('d M Y') }}</td>
                            <td><span class="badge-gray">{{ $p->payment_mode }}</span></td>
                            <td class="text-xs">{{ $p->txn_ref ?: '—' }}</td>
                            <td class="num font-semibold">{{ money_in($p->total) }}</td>
                            <td>
                                @if ($p->screenshot)
                                    <a href="{{ asset('storage/' . $p->screenshot) }}" target="_blank" class="text-xs text-brand-600 hover:underline">View</a>
                                @else — @endif
                            </td>
                            <td class="whitespace-nowrap text-right">
                                <a href="{{ route('admin.payments.show', $p) }}" class="btn-outline btn-sm">Open</a>
                            </td>
                        </tr>
                    @empty
                        <x-empty cols="10" message="Nothing waiting for approval 🎉" />
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($payments->hasPages())
            <div class="border-t border-ink-200 px-5 py-3 dark:border-ink-800">{{ $payments->links() }}</div>
        @endif
    </div>
</form>

@endsection
