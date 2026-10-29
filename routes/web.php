<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\AgentController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FinanceReportController;
use App\Http\Controllers\Admin\MasterController;
use App\Http\Controllers\Admin\RateController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PointController;
use App\Http\Controllers\Admin\ReconciliationController;
use App\Http\Controllers\Admin\SchemeController;
use App\Http\Controllers\Admin\SchemeReportController;
use App\Http\Controllers\Admin\VoucherController;
use App\Http\Controllers\Admin\WishController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Auth
|--------------------------------------------------------------------------
*/
Route::get('/', fn () => redirect()->to(url('/login')));

Route::get('/download/app', function () {
    $path = \App\Models\Setting::get('android_apk_path', 'releases/scheme-erp.apk');
    abort_unless(Storage::disk('public')->exists($path), 404);

    $version = preg_replace('/[^A-Za-z0-9._-]/', '-', (string) \App\Models\Setting::get('android_apk_version', 'latest'));
    $appName = preg_replace('/[^A-Za-z0-9._-]/', '-', (string) \App\Models\Setting::get('android_apk_name', 'Scheme-ERP'));

    return Storage::disk('public')->download(
        $path,
        "{$appName}-{$version}.apk",
        ['Content-Type' => 'application/vnd.android.package-archive']
    );
})->name('app.download');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

/*
|--------------------------------------------------------------------------
| Admin panel
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/change-password', [LoginController::class, 'showChangePassword'])->name('password.edit');
    Route::post('/change-password', [LoginController::class, 'changePassword'])->name('password.update');

    /* ---------------- CUSTOMERS ---------------- */
    Route::prefix('customers')->name('customers.')->group(function () {
        Route::get('/', [CustomerController::class, 'index'])->name('index');
        Route::get('/create', [CustomerController::class, 'create'])->name('create');
        Route::post('/', [CustomerController::class, 'store'])->name('store');
        Route::get('/{customer}', [CustomerController::class, 'show'])->name('show');
        Route::get('/{customer}/edit', [CustomerController::class, 'edit'])->name('edit');
        Route::put('/{customer}', [CustomerController::class, 'store'])->name('update');
        Route::delete('/{customer}', [CustomerController::class, 'destroy'])->name('destroy');
        Route::post('/{customer}/enroll', [CustomerController::class, 'enroll'])->name('enroll');
    });

    Route::get('/ajax/states/{country}', [CustomerController::class, 'states'])->name('ajax.states');
    Route::get('/ajax/districts/{state}', [CustomerController::class, 'districts'])->name('ajax.districts');
    Route::get('/ajax/customer-schemes/{customer}', [PaymentController::class, 'customerSchemes'])->name('ajax.customer-schemes');
    Route::get('/ajax/ledgers', [AccountController::class, 'search'])->name('ajax.ledgers');

    /* ---------------- AGENTS ---------------- */
    Route::prefix('agents')->name('agents.')->group(function () {
        Route::get('/', [AgentController::class, 'index'])->name('index');
        Route::get('/create', [AgentController::class, 'create'])->name('create');
        Route::post('/', [AgentController::class, 'store'])->name('store');
        Route::get('/payouts', [AgentController::class, 'payouts'])->name('payouts');
        Route::post('/payouts', [AgentController::class, 'storePayout'])->name('payouts.store');
        Route::get('/pending-commission', [AgentController::class, 'pendingCommission'])->name('pending-commission');
        Route::get('/{agent}', [AgentController::class, 'show'])->name('show');
        Route::get('/{agent}/edit', [AgentController::class, 'edit'])->name('edit');
        Route::put('/{agent}', [AgentController::class, 'store'])->name('update');
        Route::delete('/{agent}', [AgentController::class, 'destroy'])->name('destroy');
    });

    /* ---------------- SCHEMES ---------------- */
    Route::prefix('schemes')->name('schemes.')->group(function () {
        Route::get('/', [SchemeController::class, 'index'])->name('index');
        Route::get('/create', [SchemeController::class, 'create'])->name('create');
        Route::post('/', [SchemeController::class, 'store'])->name('store');
        Route::get('/enrollments', [SchemeController::class, 'enrollments'])->name('enrollments');
        Route::get('/matured', [SchemeController::class, 'matured'])->name('matured');
        Route::get('/expiring', [SchemeController::class, 'expiring'])->name('expiring');
        Route::get('/enrollment/{enrollment}', [SchemeController::class, 'showEnrollment'])->name('enrollment');
        Route::post('/enrollment/{enrollment}/notify-maturity', [SchemeController::class, 'notifyMaturity'])->name('notify-maturity');
        Route::post('/enrollment/{enrollment}/block', [SchemeController::class, 'block'])->name('block');
        Route::post('/enrollment/{enrollment}/redeem', [SchemeController::class, 'redeem'])->name('redeem');
        Route::get('/{scheme}/edit', [SchemeController::class, 'edit'])->name('edit');
        Route::put('/{scheme}', [SchemeController::class, 'store'])->name('update');
        Route::delete('/{scheme}', [SchemeController::class, 'destroy'])->name('destroy');
    });

    /* ---------------- PAYMENTS ---------------- */
    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/', [PaymentController::class, 'index'])->name('index');
        Route::get('/pending', [PaymentController::class, 'pending'])->name('pending');
        Route::get('/create', [PaymentController::class, 'create'])->name('create');
        Route::post('/', [PaymentController::class, 'store'])->name('store');
        Route::post('/bulk-approve', [PaymentController::class, 'bulkApprove'])->name('bulk-approve');
        Route::get('/{payment}', [PaymentController::class, 'show'])->name('show');
        Route::post('/{payment}/approve', [PaymentController::class, 'approve'])->name('approve');
        Route::post('/{payment}/reject', [PaymentController::class, 'reject'])->name('reject');
    });

    /* ---------------- POINTS ---------------- */
    Route::prefix('points')->name('points.')->group(function () {
        Route::get('/', [PointController::class, 'index'])->name('index');
        Route::get('/redeem-report', [PointController::class, 'redeemReport'])->name('redeem-report');
        Route::get('/referrals', [PointController::class, 'referrals'])->name('referrals');
        Route::post('/referrals/setting', [PointController::class, 'saveReferralSetting'])->name('referrals.setting');
        Route::post('/add', [PointController::class, 'add'])->name('add');
        Route::post('/redeem', [PointController::class, 'redeem'])->name('redeem');
    });

    /* ---------------- CUSTOMER WISHES ---------------- */
    Route::get('/wishes', [WishController::class, 'index'])->name('wishes.index');
    Route::post('/wishes', [WishController::class, 'store'])->name('wishes.store');

    /* ---------------- OPERATIONAL REPORTS ---------------- */
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/customers', [SchemeReportController::class, 'customers'])->name('customers');
        Route::get('/joiners', [SchemeReportController::class, 'joiners'])->name('joiners');
        Route::get('/schemes', [SchemeReportController::class, 'schemes'])->name('schemes');
        Route::get('/collection', [SchemeReportController::class, 'collection'])->name('collection');
        Route::get('/pending', [SchemeReportController::class, 'pending'])->name('pending');
        Route::get('/agent-pending', [SchemeReportController::class, 'agentPending'])->name('agent-pending');
        Route::get('/gram-summary', [SchemeReportController::class, 'gramSummary'])->name('gram-summary');
    });

    /* Today's gold rate, set from the dashboard */
    Route::post('/rates', [RateController::class, 'store'])->name('rates.store');

    /* ---------------- MASTERS ---------------- */
    Route::get('/settings', [MasterController::class, 'settings'])->name('settings');
    Route::post('/settings', [MasterController::class, 'saveSettings'])->name('settings.save');
    Route::post('/settings/android-app', [MasterController::class, 'saveAndroidApp'])->name('settings.android-app');
    Route::post('/settings/sql', [MasterController::class, 'runSql'])->middleware('admin:superadmin')->name('settings.sql');
    Route::post('/settings/reset-data', [MasterController::class, 'resetData'])->middleware('admin:superadmin')->name('settings.reset-data');

    Route::prefix('master')->name('master.')->group(function () {
        Route::get('/{entity}', [MasterController::class, 'index'])->name('index');
        Route::post('/{entity}', [MasterController::class, 'store'])->name('store');
        Route::put('/{entity}/{id}', [MasterController::class, 'store'])->name('update');
        Route::delete('/{entity}/{id}', [MasterController::class, 'destroy'])->name('destroy');
        Route::post('/{entity}/{id}/toggle', [MasterController::class, 'toggle'])->name('toggle');
    });
});

/*
|--------------------------------------------------------------------------
| Accounts module
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'admin:accounts'])->prefix('accounts')->name('accounts.')->group(function () {

    /* Chart of accounts */
    Route::get('/bases', [AccountController::class, 'bases'])->name('bases');
    Route::post('/bases', [AccountController::class, 'storeBase'])->name('bases.store');
    Route::put('/bases/{gbscode}', [AccountController::class, 'storeBase'])->name('bases.update');

    Route::get('/groups', [AccountController::class, 'groups'])->name('groups');
    Route::post('/groups', [AccountController::class, 'storeGroup'])->name('groups.store');
    Route::put('/groups/{grcode}', [AccountController::class, 'storeGroup'])->name('groups.update');
    Route::delete('/groups/{grcode}', [AccountController::class, 'destroyGroup'])->name('groups.destroy');

    Route::get('/ledgers', [AccountController::class, 'ledgers'])->name('ledgers');
    Route::get('/ledgers/create', [AccountController::class, 'createLedger'])->name('ledgers.create');
    Route::post('/ledgers', [AccountController::class, 'storeLedger'])->name('ledgers.store');
    Route::get('/ledgers/{accode}/edit', [AccountController::class, 'editLedger'])->name('ledgers.edit');
    Route::put('/ledgers/{accode}', [AccountController::class, 'storeLedger'])->name('ledgers.update');
    Route::delete('/ledgers/{accode}', [AccountController::class, 'destroyLedger'])->name('ledgers.destroy');

    /* Vouchers */
    Route::prefix('vouchers')->name('vouchers.')->group(function () {
        Route::get('/', [VoucherController::class, 'index'])->name('index');
        Route::get('/create', [VoucherController::class, 'create'])->name('create');
        Route::post('/', [VoucherController::class, 'store'])->name('store');
        Route::get('/{sno}', [VoucherController::class, 'show'])->name('show');
        Route::post('/{sno}/cancel', [VoucherController::class, 'cancel'])->name('cancel');
        Route::delete('/{sno}', [VoucherController::class, 'destroy'])->name('destroy');
    });

    /* Closing stock valuation - feeds the Trading A/c and the Balance Sheet */
    Route::get('/stock-values', [AccountController::class, 'stockValues'])->name('stock-values');
    Route::post('/stock-values', [AccountController::class, 'storeStockValue'])->name('stock-values.store');
    Route::delete('/stock-values/{id}', [AccountController::class, 'destroyStockValue'])->name('stock-values.destroy');

    /* Bank reconciliation - proving direct UPI collections arrived */
    Route::prefix('reconciliation')->name('reconciliation.')->group(function () {
        Route::get('/', [ReconciliationController::class, 'index'])->name('index');
        Route::post('/import', [ReconciliationController::class, 'import'])->name('import');
        Route::post('/rematch', [ReconciliationController::class, 'rematch'])->name('rematch');
        Route::post('/approve-matched', [ReconciliationController::class, 'approveMatched'])->name('approve-matched');
        Route::post('/{id}/approve', [ReconciliationController::class, 'approve'])->name('approve');
        Route::post('/{id}/link', [ReconciliationController::class, 'link'])->name('link');
        Route::post('/{id}/ignore', [ReconciliationController::class, 'ignore'])->name('ignore');
        Route::post('/{id}/reset', [ReconciliationController::class, 'reset'])->name('reset');
    });

    /* Financial reports */
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/general-ledger', [FinanceReportController::class, 'generalLedger'])->name('general-ledger');
        Route::get('/trial-balance', [FinanceReportController::class, 'trialBalance'])->name('trial-balance');
        Route::get('/trial-balance-group', [FinanceReportController::class, 'trialBalanceGroup'])->name('trial-balance-group');
        Route::get('/profit-loss', [FinanceReportController::class, 'profitLoss'])->name('profit-loss');
        Route::get('/trading-profit-loss', [FinanceReportController::class, 'tradingProfitLoss'])->name('trading-profit-loss');
        Route::get('/balance-sheet', [FinanceReportController::class, 'balanceSheet'])->name('balance-sheet');
        Route::get('/balance-sheet-schedules', [FinanceReportController::class, 'balanceSheetSchedules'])->name('balance-sheet-schedules');
        Route::get('/cash-flow', [FinanceReportController::class, 'cashFlow'])->name('cash-flow');
        Route::get('/receipts-payments', [FinanceReportController::class, 'receiptsPayments'])->name('receipts-payments');
        Route::get('/group-summary', [FinanceReportController::class, 'groupSummary'])->name('group-summary');
        Route::get('/receivable-payable', [FinanceReportController::class, 'receivablePayable'])->name('receivable-payable');
        Route::get('/journal-register', [FinanceReportController::class, 'journalRegister'])->name('journal-register');
        Route::get('/daybook', [FinanceReportController::class, 'dayBook'])->name('daybook');
        Route::get('/cash-book', [FinanceReportController::class, 'cashBook'])->name('cash-book');
        Route::get('/bank-book', [FinanceReportController::class, 'bankBook'])->name('bank-book');
        Route::get('/outstanding', [FinanceReportController::class, 'outstanding'])->name('outstanding');
        Route::get('/export/{report}', [FinanceReportController::class, 'export'])->name('export');
    });
});

/*
|--------------------------------------------------------------------------
| Storage fallback
|--------------------------------------------------------------------------
| Serves files from storage/app/public when public/storage is not a working
| symlink. Many shared hosts disable symlink() so `php artisan storage:link`
| silently leaves nothing behind, and every banner, product photo and payment
| screenshot then 403s.
|
| When the symlink DOES exist Apache serves the file first and this route is
| never reached, so it costs nothing.
*/
Route::get('/storage/{path}', function (string $path) {
    // Reject traversal before touching the filesystem.
    abort_if(str_contains($path, '..'), 404);

    $root = realpath(Storage::disk('public')->path(''));
    $file = realpath($root.DIRECTORY_SEPARATOR.ltrim($path, '/'));

    // realpath() resolves any symlink or casing trick, so this is the check that
    // actually keeps the response inside storage/app/public.
    abort_unless($file && is_file($file) && str_starts_with($file, $root.DIRECTORY_SEPARATOR), 404);

    return response()->file($file, [
        'Cache-Control' => 'public, max-age=604800',
    ])->setAutoLastModified();
})
    ->where('path', '.*')
    ->name('storage.fallback')
    // Serving an image has no business opening a database session: with
    // SESSION_DRIVER=database every picture cost a session read/write, and any
    // database hiccup turned the whole page's images into 500s.
    ->withoutMiddleware([
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ]);
