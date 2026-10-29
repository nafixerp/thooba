<?php

namespace App\Providers;

use App\Services\AccountingService;
use App\Services\FinancialReportService;
use App\Services\SchemeService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AccountingService::class);
        $this->app->singleton(FinancialReportService::class);
        $this->app->singleton(SchemeService::class);
    }

    public function boot(): void
    {
        URL::forceRootUrl(config('app.url'));

        /* ₹ 1,23,456.78  (Indian grouping) */
        Blade::directive('money', function ($expr) {
            return "<?php echo money_in($expr); ?>";
        });

        Blade::directive('drcr', function ($expr) {
            return "<?php echo \App\Models\Accountm::formatDrCr((float)($expr)); ?>";
        });
    }
}
