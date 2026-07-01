<?php

namespace App\Providers;

use App\Models\Report;
use App\Models\Complaint;
use App\Observers\ReportObserver;
use App\Observers\ComplaintObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Report::observe(ReportObserver::class);
        Complaint::observe(ComplaintObserver::class);
    }
}
