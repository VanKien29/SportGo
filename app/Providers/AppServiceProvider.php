<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\Complaint;
use App\Models\Report;
use App\Models\SlotLock;
use App\Observers\BookingObserver;
use App\Observers\ComplaintObserver;
use App\Observers\ReportObserver;
use App\Observers\SlotLockObserver;
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
        Booking::observe(BookingObserver::class);
        SlotLock::observe(SlotLockObserver::class);
    }
}
