<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Customer;
use App\Models\CustomerScheme;
use App\Models\Daybook;
use App\Models\Installment;
use App\Models\Metal;
use App\Models\MetalRate;
use App\Models\Payment;
use App\Services\AccountingService;
use App\Services\FinancialReportService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(
        protected FinancialReportService $reports,
        protected AccountingService $accounts
    ) {}

    public function index()
    {
        $today = now()->toDateString();
        [$fyFrom, $fyTo] = $this->accounts->financialYearRange($today);

        /* ---------------- KPI cards ---------------- */
        $stats = [
            'customers' => Customer::where('status', true)->count(),
            'customers_new' => Customer::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)->count(),
            'agents' => Agent::where('status', true)->count(),
            'joiners_today' => Customer::whereDate('created_at', $today)->count(),
            'enrolments_today' => CustomerScheme::whereDate('created_at', $today)->count(),
            'active_schemes' => CustomerScheme::where('status', 'ACTIVE')->count(),
            'matured_schemes' => CustomerScheme::where('status', 'MATURED')->count(),

            'collection_today' => (float) Payment::approved()->whereDate('payment_date', $today)->sum('total'),
            'collection_month' => (float) Payment::approved()
                ->whereMonth('payment_date', now()->month)
                ->whereYear('payment_date', now()->year)->sum('total'),
            'collection_fy' => (float) Payment::approved()
                ->whereBetween('payment_date', [$fyFrom, $fyTo])->sum('total'),

            'pending_payments' => Payment::pending()->count(),
            'pending_amount' => (float) Payment::pending()->sum('total'),

            'overdue' => Installment::where('status', 'OVERDUE')->count(),
            'overdue_amount' => (float) Installment::where('status', 'OVERDUE')
                ->sum(DB::raw('amount - paid_amount')),
            'due_today' => Installment::pending()->whereDate('due_date', $today)->count(),
            'due_week' => Installment::pending()
                ->whereBetween('due_date', [$today, now()->addDays(7)->toDateString()])->count(),

            'total_gram' => (float) CustomerScheme::sum('total_gram'),
            'gold_rate' => MetalRate::latest_rate(),

            'cash_balance' => $this->balance(config('scheme.accounts.cash')),
            'bank_balance' => $this->balance(config('scheme.accounts.bank')),
        ];

        /* ---------------- today's rates, for the inline editor ---------------- */
        $metals = Metal::where('status', true)->orderBy('id')->get(['id', 'name', 'code', 'unit']);
        $rateToday = MetalRate::whereDate('rate_date', $today)
            ->where('status', true)
            ->orderByDesc('id')
            ->get()
            ->keyBy('metal_id');

        /* ---------------- P&L snapshot ---------------- */
        $pl = $this->reports->profitAndLoss($fyFrom->toDateString(), $today);
        $stats['income_fy'] = $pl['total_income'];
        $stats['expense_fy'] = $pl['total_expense'];
        $stats['profit_fy'] = $pl['net_profit'];

        /* ---------------- Charts ---------------- */
        $months = collect(range(11, 0))->map(fn ($i) => now()->subMonths($i)->startOfMonth());

        $collectionSeries = $months->map(function (Carbon $m) {
            return [
                'label' => $m->format('M y'),
                'value' => (float) Payment::approved()
                    ->whereYear('payment_date', $m->year)
                    ->whereMonth('payment_date', $m->month)
                    ->sum('total'),
            ];
        })->values();

        $incomeExpense = $months->map(function (Carbon $m) {
            $from = $m->copy()->startOfMonth()->toDateString();
            $to = $m->copy()->endOfMonth()->toDateString();

            $income = -(float) Daybook::posted()
                ->join('accountm', 'accountm.accode', '=', 'daybook.accode')
                ->where('accountm.bshead', 'PL')
                ->whereIn('accountm.actype', ['INCOME'])
                ->whereBetween('daybook.voucher_date', [$from, $to])
                ->sum('daybook.amount');

            $expense = (float) Daybook::posted()
                ->join('accountm', 'accountm.accode', '=', 'daybook.accode')
                ->where('accountm.bshead', 'PL')
                ->whereIn('accountm.actype', ['EXPENSE'])
                ->whereBetween('daybook.voucher_date', [$from, $to])
                ->sum('daybook.amount');

            return ['label' => $m->format('M y'), 'income' => round($income, 2), 'expense' => round($expense, 2)];
        })->values();

        $modeSplit = Payment::approved()
            ->whereBetween('payment_date', [$fyFrom, $fyTo])
            ->select('payment_mode', DB::raw('SUM(total) as total'))
            ->groupBy('payment_mode')
            ->pluck('total', 'payment_mode');

        /* ---------------- Lists ---------------- */
        $recentPayments = Payment::with(['customer:id,name,reg_no,phone', 'customerScheme:id,account_no'])
            ->latest('id')->limit(8)->get();

        $pendingList = Payment::with('customer:id,name,phone')
            ->pending()->latest('id')->limit(6)->get();

        $expiring = Installment::with('customerScheme.customer:id,name,phone')
            ->whereIn('status', ['PENDING', 'OVERDUE'])
            ->whereBetween('due_date', [now()->subDays(3), now()->addDays(7)])
            ->orderBy('due_date')->limit(8)->get();

        $topAgents = Agent::withSum(['payments as collected' => fn ($q) => $q->where('status', 'APPROVED')], 'total')
            ->orderByDesc('collected')->limit(5)->get();

        return view('admin.dashboard.index', compact('metals', 'rateToday', 
            'stats', 'collectionSeries', 'incomeExpense', 'modeSplit',
            'recentPayments', 'pendingList', 'expiring', 'topAgents', 'pl'
        ));
    }

    protected function balance(string $accode): float
    {
        $acc = \App\Models\Accountm::find($accode);

        return $acc ? $acc->balanceAsOn() : 0.0;
    }
}
