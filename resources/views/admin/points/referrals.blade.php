@extends('layouts.app')
@section('title', 'Referrals')

@section('content')
<div class="grid grid-cols-1 gap-5 lg:grid-cols-4">
    <div class="card lg:col-span-3">
        <div class="card-head"><h3 class="card-title">Referral History</h3></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Code</th><th>Referrer</th><th>Referred Customer</th><th class="text-right">Points</th><th>Date</th></tr></thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr>
                            <td class="font-mono text-xs font-semibold">{{ $r->code }}</td>
                            <td><a href="{{ route('admin.customers.show', $r->customer_id) }}" class="font-medium hover:text-brand-600 hover:underline">{{ $r->customer->name ?? '—' }}</a></td>
                            <td>{{ $r->referred->name ?? '—' }}<div class="text-xs text-ink-500">{{ $r->referred->phone ?? '' }}</div></td>
                            <td class="num font-semibold">{{ number_format($r->points, 0) }}</td>
                            <td class="whitespace-nowrap">{{ $r->referral_date->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <x-empty cols="5" message="No referrals yet" />
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($rows->hasPages())<div class="border-t border-ink-200 px-5 py-3 dark:border-ink-800">{{ $rows->links() }}</div>@endif
    </div>

    <div class="card h-fit">
        <div class="card-head"><h3 class="card-title">Default Referral Points</h3></div>
        <form method="POST" action="{{ route('admin.points.referrals.setting') }}" class="card-pad space-y-3">
            @csrf
            <div>
                <label class="label">Points per referral</label>
                <input type="number" step="0.01" name="referral_points" class="input" required value="{{ $defaultPoints }}">
                <p class="form-hint">Awarded to the referrer when a new customer registers with their code.</p>
            </div>
            <button class="btn-primary w-full">Save</button>
        </form>
    </div>
</div>
@endsection
