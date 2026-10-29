@extends('layouts.app')
@section('title', 'Scheme Master')

@section('content')
<x-page-header title="Schemes" sub="Products customers can enroll in">
    <a href="{{ route('admin.schemes.create') }}" class="btn-dark">+ New Scheme</a>
</x-page-header>

<div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
    @forelse ($schemes as $s)
        <div class="card card-pad">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h3 class="truncate text-[15px] font-semibold">{{ $s->name }}</h3>
                    <p class="font-mono text-xs text-ink-500">{{ $s->code }}</p>
                </div>
                <x-status :value="$s->status ? 'ACTIVE' : 'CLOSED'" />
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <div>
                    <div class="text-[11px] uppercase tracking-wide text-ink-500">Installment</div>
                    <div class="font-mono font-semibold">{{ cur($s->amount) }}</div>
                </div>
                <div>
                    <div class="text-[11px] uppercase tracking-wide text-ink-500">Interval</div>
                    <div class="font-medium">{{ ucfirst(strtolower($s->payment_interval)) }}</div>
                </div>
                <div>
                    <div class="text-[11px] uppercase tracking-wide text-ink-500">Installments</div>
                    <div class="font-semibold">{{ $s->total_installments }}</div>
                </div>
                <div>
                    <div class="text-[11px] uppercase tracking-wide text-ink-500">Maturity Value</div>
                    <div class="font-mono font-semibold text-brand-600">{{ cur($s->maturity_value) }}</div>
                </div>
            </div>

            @if ($s->bonus_type !== 'NONE')
                <div class="mt-3 rounded-lg bg-brand-50 px-3 py-2 text-xs text-brand-800 dark:bg-brand-500/10 dark:text-brand-300">
                    Bonus: {{ $s->bonus_value }}
                    {{ $s->bonus_type === 'PERCENT' ? '%' : ($s->bonus_type === 'INSTALLMENT' ? 'free installment(s)' : '₹') }}
                </div>
            @endif

            <div class="mt-4 flex items-center justify-between border-t border-ink-100 pt-3 dark:border-ink-800">
                <span class="text-xs text-ink-500">{{ $s->enrollments_count }} enrollments</span>
                <a href="{{ route('admin.schemes.edit', $s) }}" class="btn-outline btn-sm">Edit</a>
            </div>
        </div>
    @empty
        <div class="card card-pad col-span-full py-16 text-center">
            <p class="text-sm text-ink-500">No schemes yet</p>
            <a href="{{ route('admin.schemes.create') }}" class="btn-primary btn-sm mt-3">Create the first scheme</a>
        </div>
    @endforelse
</div>

@if ($schemes->hasPages())<div class="mt-5">{{ $schemes->links() }}</div>@endif
@endsection
