@extends('layouts.app')
@section('title', $customer->name)
@section('subtitle', $customer->reg_no . ' · ' . $customer->phone)

@section('content')

<div class="mb-5 flex flex-wrap gap-2 no-print">
    <a href="{{ route('admin.customers.index') }}" class="btn-outline">&larr; Back</a>
    <a href="{{ route('admin.customers.edit', $customer) }}" class="btn-outline">Edit</a>
    <a href="{{ route('admin.payments.create', ['customer_id' => $customer->id]) }}" class="btn-primary">Record Payment</a>
    @if ($customer->accode)
        <a href="{{ route('accounts.reports.general-ledger', ['accode' => $customer->accode]) }}" class="btn-dark">Full Ledger</a>
    @endif
</div>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-stat label="Wallet Balance" :value="cur($customer->wallet_amount)" icon="wallet" tone="brand" />
    <x-stat label="Gold Accumulated" :value="number_format($customer->total_gram, 3) . ' g'" icon="gem" tone="amber" />
    <x-stat label="Active Schemes" :value="$customer->schemes->where('status', 'ACTIVE')->count()" icon="layers" tone="violet" />
    <x-stat label="Reward Points" :value="number_format($customer->point->balance_point ?? 0, 0)" icon="coins" tone="green" />
</div>

<div class="mt-5 overflow-hidden rounded-2xl p-5 text-white shadow-lg"
     style="background: linear-gradient(135deg, {{ $privilege['card_color'] ?: '#1f2937' }}, #111827)">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="text-xs font-semibold uppercase tracking-[0.22em] text-white/70">Privilege Card</div>
            <div class="mt-1 text-2xl font-black">{{ $privilege['tier'] ?: 'Member' }}</div>
            <div class="mt-1 text-sm text-white/75">{{ $privilege['benefits'] ?: 'Configure tiers under Points & Referral.' }}</div>
        </div>
        <div class="text-right">
            <div class="text-xs text-white/65">{{ $privilege['basis'] === 'GRAM' ? 'Gold accumulated' : 'Approved sales' }}</div>
            <div class="font-mono text-xl font-bold">{{ $privilege['basis'] === 'GRAM' ? number_format($privilege['grams'], 3).' g' : money_in($privilege['sales']) }}</div>
            <div class="text-xs text-white/70">{{ $privilege['basis'] === 'GRAM' ? number_format($privilege['points_per_gram'], 2).' points / gram' : number_format($privilege['points_per_100'], 2).' points / Rs 100' }}</div>
        </div>
    </div>
    @if ($privilege['next_tier'])
        <div class="mt-4 border-t border-white/15 pt-3 text-xs text-white/75">
            {{ $privilege['next_basis'] === 'GRAM' ? number_format($privilege['to_next'], 3).' g' : money_in($privilege['to_next']) }} more to reach {{ $privilege['next_tier'] }}.
        </div>
    @endif
</div>

<div class="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-3">

    {{-- Profile --}}
    <div class="card h-fit">
        <div class="card-head"><h3 class="card-title">Profile</h3><x-status :value="$customer->kyc_status" /></div>
        <div class="card-pad">
            <div class="mb-4 flex items-center gap-3">
                @if ($customer->photo)
                    <img src="{{ asset('storage/' . $customer->photo) }}" class="h-14 w-14 rounded-xl object-cover" alt="">
                @else
                    <span class="grid h-14 w-14 place-items-center rounded-xl bg-gradient-to-br from-brand-300 to-brand-600 text-lg font-bold text-white">
                        {{ strtoupper(substr($customer->name, 0, 2)) }}
                    </span>
                @endif
                <div>
                    <div class="font-semibold">{{ $customer->name }}</div>
                    <div class="text-xs text-ink-500">Joined {{ $customer->join_date?->format('d M Y') }}</div>
                </div>
            </div>

            <dl class="space-y-2.5 text-sm">
                @foreach ([
                    'Phone' => $customer->phone,
                    'Email' => $customer->email ?: '—',
                    'District' => $customer->district?->name ?: '—',
                    'Agent' => $customer->agent?->name ?: '—',
                    'Referral Code' => $customer->referral_code ?: '—',
                    'Ledger (accode)' => $customer->accode ?: 'not created',
                    'Nominee' => $customer->nominee_name ?: '—',
                ] as $k => $v)
                    <div class="flex justify-between gap-3">
                        <dt class="shrink-0 text-ink-500">{{ $k }}</dt>
                        <dd class="truncate text-right font-medium">{{ $v }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>

    {{-- Schemes --}}
    <div class="xl:col-span-2 space-y-5">
        <div class="card">
            <div class="card-head">
                <h3 class="card-title">Scheme Enrollments</h3>
                <button x-data @click="$refs.enroll.classList.toggle('hidden')" class="btn-outline btn-sm">+ Enroll</button>
            </div>

            <form x-ref="enroll" method="POST" action="{{ route('admin.customers.enroll', $customer) }}"
                  class="hidden border-b border-ink-200 bg-ink-50 p-5 dark:border-ink-800 dark:bg-ink-950/40">
                @csrf
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                    <div class="sm:col-span-2">
                        <label class="label">Scheme</label>
                        <select name="scheme_id" class="input" required>
                            @foreach ($schemes as $s)
                                <option value="{{ $s->id }}">{{ $s->name }} — {{ cur($s->amount) }} × {{ $s->total_installments }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Amount</label>
                        <input type="number" step="0.01" name="installment_amount" class="input" placeholder="scheme default">
                    </div>
                    <div>
                        <label class="label">Start Date</label>
                        <input type="date" name="start_date" class="input" required value="{{ now()->toDateString() }}">
                    </div>
                    <div>
                        {{-- Proposed from the highest book in use. Overwrite it when
                             the book in hand says something else. --}}
                        <label class="label">Book No</label>
                        <input name="book_no" class="input"
                               value="{{ \App\Models\CustomerScheme::nextBookNo() }}">
                    </div>
                </div>
                <button class="btn-primary mt-3">Create Enrollment</button>
            </form>

            <div class="divide-row">
                @forelse ($customer->schemes as $cs)
                    <div class="px-5 py-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('admin.schemes.enrollment', $cs) }}" class="font-medium hover:text-brand-600 hover:underline">
                                    {{ $cs->scheme->name }}
                                </a>
                                <div class="text-xs text-ink-500">
                                    @if ($cs->book_no)Book {{ $cs->book_no }} · @endif{{ $cs->account_no }} · {{ cur($cs->installment_amount) }} {{ strtolower($cs->payment_interval) }}
                                    · started {{ $cs->start_date?->format('d M Y') }}
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                @if (in_array($cs->status, ['ACTIVE', 'MATURED']))
                                    <a href="{{ route('admin.schemes.enrollment', $cs) }}#scheme-closure" class="btn-dark btn-sm">Finish / Close</a>
                                @endif
                                <x-status :value="$cs->status" />
                            </div>
                        </div>

                        <div class="mt-3 flex items-center gap-3">
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-ink-200 dark:bg-ink-800">
                                <div class="h-full rounded-full bg-gradient-to-r from-brand-400 to-brand-600"
                                     style="width: {{ min($cs->progress, 100) }}%"></div>
                            </div>
                            <span class="shrink-0 text-xs font-medium text-ink-500">
                                {{ $cs->paid_installments }}/{{ $cs->total_installments }} · {{ $cs->progress }}%
                            </span>
                        </div>

                        <div class="mt-2 flex flex-wrap gap-4 text-xs text-ink-500">
                            <span>Paid <span class="font-mono font-semibold text-ink-700 dark:text-ink-200">{{ cur($cs->total_paid) }}</span></span>
                            <span>GST <span class="font-mono">{{ money_in($cs->total_gst) }}</span></span>
                            <span>Gram <span class="font-mono font-semibold text-ink-700 dark:text-ink-200">{{ number_format($cs->total_gram, 3) }}</span></span>
                            <span>Maturity {{ $cs->maturity_date?->format('d M Y') ?: '—' }}</span>
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-ink-500">Not enrolled in any scheme yet</p>
                @endforelse
            </div>
        </div>

        {{-- Payments --}}
        <div class="card">
            <div class="card-head"><h3 class="card-title">Recent Payments</h3></div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Receipt</th><th>Date</th><th>Mode</th><th class="text-right">Amount</th>
                            <th class="text-right">GST</th><th class="text-right">Gram</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($payments as $p)
                            <tr>
                                <td><a href="{{ route('admin.payments.show', $p) }}" class="font-mono text-[12.5px] text-brand-600 hover:underline">{{ $p->receipt_no }}</a></td>
                                <td class="whitespace-nowrap">{{ $p->payment_date->format('d M Y') }}</td>
                                <td><span class="badge-gray">{{ $p->payment_mode }}</span></td>
                                <td class="num font-semibold">{{ money_in($p->amount) }}</td>
                                <td class="num text-ink-500">{{ money_in($p->tax) }}</td>
                                <td class="num">{{ number_format($p->gram, 3) }}</td>
                                <td><x-status :value="$p->status" /></td>
                            </tr>
                        @empty
                            <x-empty cols="7" message="No payments yet" />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Ledger --}}
        @if ($ledger)
            <div class="card">
                <div class="card-head">
                    <h3 class="card-title">Account Ledger ({{ $customer->accode }})</h3>
                    <span class="text-sm font-semibold">Closing: {{ money_in(abs($ledger['closing'])) }} {{ $ledger['closing_side'] }}</span>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th>Date</th><th>Particulars</th><th>Voucher</th>
                                <th class="text-right">Debit</th><th class="text-right">Credit</th><th class="text-right">Balance</th></tr>
                        </thead>
                        <tbody>
                            @forelse (array_slice($ledger['lines'], -15) as $l)
                                <tr>
                                    <td class="whitespace-nowrap text-ink-600 dark:text-ink-400">{{ $l['date'] }}</td>
                                    <td>{{ $l['particulars'] }}</td>
                                    <td class="font-mono text-xs text-ink-500">{{ $l['voucher_no'] }}</td>
                                    <td class="num">{{ $l['debit'] ? money_in($l['debit']) : '' }}</td>
                                    <td class="num">{{ $l['credit'] ? money_in($l['credit']) : '' }}</td>
                                    <td class="num font-semibold">{{ money_in(abs($l['balance'])) }} {{ $l['balance_side'] }}</td>
                                </tr>
                            @empty
                                <x-empty cols="6" message="No ledger entries" />
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>

@endsection
