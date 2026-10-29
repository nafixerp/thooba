@extends('layouts.app')
@section('title', 'Dashboard')
@section('subtitle', 'Overview of collections, schemes and finances')

@section('content')

{{-- ============ TODAY'S RATE ============ --}}
{{-- Top of the dashboard because it is the first thing set each morning, and
     the whole app quotes it: gold balances, scheme cards, jewellery prices. --}}
<form method="POST" action="{{ route('admin.rates.store') }}" class="card mb-4">
    @csrf
    <div class="card-head">
        <h3 class="card-title">Today's Gold Rate</h3>
        <span class="text-xs text-ink-500">{{ now()->format('d M Y') }}</span>
    </div>
    <div class="card-pad">
        <div class="flex flex-wrap items-end gap-4">
            @foreach ($metals as $m)
                <div class="min-w-[170px]">
                    <label class="label">{{ $m->name }} <span class="text-ink-400">/ {{ strtolower($m->unit ?? 'gram') }}</span></label>
                    <input type="number" step="0.01" min="0"
                           name="rates[{{ $m->id }}]"
                           class="input"
                           value="{{ optional($rateToday->get($m->id))->rate ?? \App\Models\MetalRate::latest_rate($m->id) ?: '' }}">
                </div>
            @endforeach

            <label class="flex items-center gap-2 pb-2 text-sm">
                <input type="checkbox" name="notify" value="1" checked
                       class="h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500 dark:border-ink-700 dark:bg-ink-900">
                Tell customers in the app
            </label>

            <button class="btn-primary">Update Rate</button>
        </div>
        <p class="mt-3 text-xs text-ink-500">
            Only a rate that actually moved is saved and announced. Customers see it
            the next time they open the app.
        </p>
    </div>
</form>

{{-- ============ KPI ROW 1 ============ --}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-stat
        label="Collection Today"
        :value="cur($stats['collection_today'])"
        :sub="'This month ' . cur($stats['collection_month'])"
        icon="wallet" tone="brand" />

    <x-stat
        label="Active Customers"
        :value="number_format($stats['customers'])"
        :sub="'+' . $stats['customers_new'] . ' this month'"
        icon="users" tone="blue"
        :href="route('admin.customers.index')" />

    <x-stat
        label="Active Schemes"
        :value="number_format($stats['active_schemes'])"
        :sub="$stats['matured_schemes'] . ' matured'"
        icon="layers" tone="violet"
        :href="route('admin.schemes.enrollments')" />

    <x-stat
        label="Gold Accumulated"
        :value="number_format($stats['total_gram'], 3) . ' g'"
        :sub="'Rate ' . cur($stats['gold_rate']) . '/g'"
        icon="gem" tone="amber" />
</div>

{{-- ============ KPI ROW 2 ============ --}}
<div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-stat
        label="Pending Approval"
        :value="number_format($stats['pending_payments'])"
        :sub="cur($stats['pending_amount']) . ' waiting'"
        icon="clock" tone="amber"
        :href="route('admin.payments.pending')" />

    <x-stat
        label="Overdue Installments"
        :value="number_format($stats['overdue'])"
        :sub="cur($stats['overdue_amount']) . ' outstanding'"
        icon="alert" tone="rose"
        :href="route('admin.reports.pending')" />

    <x-stat
        label="Cash in Hand"
        :value="cur($stats['cash_balance'])"
        :sub="'Bank ' . cur($stats['bank_balance'])"
        icon="bank" tone="green"
        :href="route('accounts.reports.cash-book')" />

    <x-stat
        label="{{ $stats['profit_fy'] >= 0 ? 'Net Profit (FY)' : 'Net Loss (FY)' }}"
        :value="cur(abs($stats['profit_fy']))"
        :sub="'Income ' . cur($stats['income_fy']) . ' · Expense ' . cur($stats['expense_fy'])"
        :icon="$stats['profit_fy'] >= 0 ? 'trend' : 'trenddown'"
        :tone="$stats['profit_fy'] >= 0 ? 'green' : 'rose'"
        :href="route('accounts.reports.profit-loss')" />
</div>

{{-- ============ TODAY AT A GLANCE ============ --}}
<div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-stat
        label="Today's Joiners"
        :value="number_format($stats['joiners_today'])"
        sub="New customer registrations"
        icon="users" tone="blue"
        :href="route('admin.reports.joiners')" />

    <x-stat
        label="Today's Enrolments"
        :value="number_format($stats['enrolments_today'])"
        sub="New scheme accounts"
        icon="layers" tone="violet"
        :href="route('admin.reports.joiners')" />

    <x-stat
        label="Payments Due Today"
        :value="number_format($stats['due_today'])"
        :sub="$stats['due_week'] . ' due in the next 7 days'"
        icon="clock" tone="amber"
        :href="route('admin.schemes.expiring')" />

    <x-stat
        label="Collection Today"
        :value="cur($stats['collection_today'])"
        :sub="'This month ' . cur($stats['collection_month'])"
        icon="wallet" tone="green"
        :href="route('admin.reports.collection')" />
</div>

{{-- ============ CHARTS ============ --}}
<div class="mt-5 grid grid-cols-1 gap-4 xl:grid-cols-3">

    <div class="card xl:col-span-2">
        <div class="card-head">
            <div>
                <h3 class="card-title">Monthly Collection</h3>
                <p class="text-xs text-ink-500">Approved payments, last 12 months</p>
            </div>
            <a href="{{ route('admin.reports.collection') }}" class="btn-outline btn-sm">Full report</a>
        </div>
        <div class="card-pad">
            <div class="h-[300px]"><canvas id="chartCollection"></canvas></div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h3 class="card-title">Payment Modes</h3>
            <span class="badge-gray">This FY</span>
        </div>
        <div class="card-pad">
            <div class="h-[240px]"><canvas id="chartModes"></canvas></div>
            <div class="mt-4 space-y-2">
                @foreach ($modeSplit as $mode => $total)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-ink-600 dark:text-ink-300">{{ $mode }}</span>
                        <span class="font-mono font-semibold">{{ cur($total) }}</span>
                    </div>
                @endforeach
                @if ($modeSplit->isEmpty())
                    <p class="py-4 text-center text-sm text-ink-500">No payments yet</p>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ============ INCOME vs EXPENSE ============ --}}
<div class="mt-5 grid grid-cols-1 gap-4 xl:grid-cols-3">

    <div class="card xl:col-span-2">
        <div class="card-head">
            <div>
                <h3 class="card-title">Income vs Expense</h3>
                <p class="text-xs text-ink-500">Straight from the daybook ledger</p>
            </div>
            <a href="{{ route('accounts.reports.profit-loss') }}" class="btn-outline btn-sm">Profit &amp; Loss</a>
        </div>
        <div class="card-pad">
            <div class="h-[290px]"><canvas id="chartPL"></canvas></div>
        </div>
    </div>

    {{-- Top agents --}}
    <div class="card">
        <div class="card-head">
            <h3 class="card-title">Top Agents</h3>
            <a href="{{ route('admin.agents.index') }}" class="text-xs font-medium text-brand-600 hover:underline">View all</a>
        </div>
        <div class="divide-row">
            @forelse ($topAgents as $i => $a)
                <a href="{{ route('admin.agents.show', $a) }}" class="flex items-center gap-3 px-5 py-3 hover:bg-ink-50 dark:hover:bg-ink-800/50">
                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-ink-100 text-xs font-bold text-ink-600 dark:bg-ink-800 dark:text-ink-300">
                        {{ $i + 1 }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-medium">{{ $a->name }}</div>
                        <div class="text-xs text-ink-500">{{ $a->agent_code }} · {{ $a->commission_percent }}%</div>
                    </div>
                    <div class="text-right">
                        <div class="font-mono text-sm font-semibold">{{ cur($a->collected ?? 0) }}</div>
                    </div>
                </a>
            @empty
                <p class="px-5 py-10 text-center text-sm text-ink-500">No agent collections yet</p>
            @endforelse
        </div>
    </div>
</div>

{{-- ============ TABLES ============ --}}
<div class="mt-5 grid grid-cols-1 gap-4 xl:grid-cols-3">

    {{-- Recent payments --}}
    <div class="card xl:col-span-2">
        <div class="card-head">
            <h3 class="card-title">Recent Payments</h3>
            <a href="{{ route('admin.payments.index') }}" class="btn-outline btn-sm">All payments</a>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Receipt</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th class="text-right">Amount</th>
                        <th class="text-right">Gram</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentPayments as $p)
                        <tr>
                            <td>
                                <a href="{{ route('admin.payments.show', $p) }}"
                                   class="font-mono text-[12.5px] font-medium text-brand-600 hover:underline">{{ $p->receipt_no }}</a>
                            </td>
                            <td>
                                <div class="font-medium">{{ $p->customer->name ?? '—' }}</div>
                                <div class="text-xs text-ink-500">{{ $p->customer->reg_no ?? '' }}</div>
                            </td>
                            <td class="whitespace-nowrap text-ink-600 dark:text-ink-400">{{ $p->payment_date->format('d M Y') }}</td>
                            <td class="num font-semibold">{{ money_in($p->total) }}</td>
                            <td class="num">{{ number_format($p->gram, 3) }}</td>
                            <td><x-status :value="$p->status" /></td>
                        </tr>
                    @empty
                        <x-empty cols="6" message="No payments recorded yet" />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pending + expiring --}}
    <div class="space-y-4">
        <div class="card">
            <div class="card-head">
                <h3 class="card-title">Awaiting Approval</h3>
                @if ($stats['pending_payments'])
                    <span class="badge-amber">{{ $stats['pending_payments'] }}</span>
                @endif
            </div>
            <div class="divide-row">
                @forelse ($pendingList as $p)
                    <div class="flex items-center gap-3 px-5 py-3">
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium">{{ $p->customer->name ?? '—' }}</div>
                            <div class="text-xs text-ink-500">{{ $p->receipt_no }} · {{ $p->payment_mode }}</div>
                        </div>
                        <span class="font-mono text-sm font-semibold">{{ money_in($p->total) }}</span>
                        <form method="POST" action="{{ route('admin.payments.approve', $p) }}">
                            @csrf
                            <button class="btn-success btn-sm">Approve</button>
                        </form>
                    </div>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-ink-500">Nothing pending 🎉</p>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h3 class="card-title">Due This Week</h3>
                <a href="{{ route('admin.schemes.expiring') }}" class="text-xs font-medium text-brand-600 hover:underline">View all</a>
            </div>
            <div class="divide-row">
                @forelse ($expiring as $i)
                    <div class="flex items-center gap-3 px-5 py-2.5">
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium">{{ $i->customerScheme->customer->name ?? '—' }}</div>
                            <div class="text-xs text-ink-500">Inst #{{ $i->installment_no }} · {{ $i->due_date->format('d M') }}</div>
                        </div>
                        <span class="font-mono text-sm">{{ money_in($i->amount) }}</span>
                        <x-status :value="$i->status" />
                    </div>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-ink-500">No dues this week</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    const t = window.chartTheme();
    const money = v => '₹' + Number(v).toLocaleString('en-IN', { maximumFractionDigits: 0 });

    Chart.defaults.font.family = "Inter, system-ui, sans-serif";
    Chart.defaults.color = t.text;

    /* ---- Collection line ---- */
    const cData = @json($collectionSeries);
    const cx = document.getElementById('chartCollection').getContext('2d');
    const grad = cx.createLinearGradient(0, 0, 0, 300);
    grad.addColorStop(0, 'rgba(212,134,29,.35)');
    grad.addColorStop(1, 'rgba(212,134,29,0)');

    new Chart(cx, {
        type: 'line',
        data: {
            labels: cData.map(d => d.label),
            datasets: [{
                label: 'Collection',
                data: cData.map(d => d.value),
                borderColor: '#d4861d',
                backgroundColor: grad,
                borderWidth: 2.5,
                fill: true,
                tension: 0.35,
                pointRadius: 0,
                pointHoverRadius: 5,
                pointHoverBackgroundColor: '#d4861d',
                pointHoverBorderColor: '#fff',
                pointHoverBorderWidth: 2,
            }],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: c => ' ' + money(c.parsed.y) } },
            },
            scales: {
                x: { grid: { display: false }, border: { display: false } },
                y: { beginAtZero: true, grid: { color: t.grid }, border: { display: false },
                     ticks: { callback: v => money(v) } },
            },
        },
    });

    /* ---- Payment modes doughnut ---- */
    const modes = @json($modeSplit);
    new Chart(document.getElementById('chartModes'), {
        type: 'doughnut',
        data: {
            labels: Object.keys(modes),
            datasets: [{
                data: Object.values(modes).map(Number),
                backgroundColor: ['#d4861d', '#0284c7', '#059669', '#7c3aed', '#e11d48', '#64748b'],
                borderWidth: 0,
                hoverOffset: 6,
            }],
        },
        options: {
            responsive: true, maintainAspectRatio: false, cutout: '66%',
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, padding: 14, usePointStyle: true } },
                tooltip: { callbacks: { label: c => ' ' + c.label + ': ' + money(c.parsed) } },
            },
        },
    });

    /* ---- Income vs expense bars ---- */
    const pl = @json($incomeExpense);
    new Chart(document.getElementById('chartPL'), {
        type: 'bar',
        data: {
            labels: pl.map(d => d.label),
            datasets: [
                { label: 'Income',  data: pl.map(d => d.income),  backgroundColor: '#059669', borderRadius: 5, maxBarThickness: 22 },
                { label: 'Expense', data: pl.map(d => d.expense), backgroundColor: '#e11d48', borderRadius: 5, maxBarThickness: 22 },
            ],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', align: 'end', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true } },
                tooltip: { callbacks: { label: c => ' ' + c.dataset.label + ': ' + money(c.parsed.y) } },
            },
            scales: {
                x: { grid: { display: false }, border: { display: false } },
                y: { beginAtZero: true, grid: { color: t.grid }, border: { display: false },
                     ticks: { callback: v => money(v) } },
            },
        },
    });
})();
</script>
@endpush
