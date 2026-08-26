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
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('auth-register', function (Request $request): array {
            return [
                Limit::perMinute(5)->by('register-ip:'.$request->ip()),
                Limit::perHour(20)->by('register-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('auth-otp-send', function (Request $request): array {
            $identifier = strtolower(trim((string) ($request->input('email') ?: $request->input('identifier'))));

            return [
                Limit::perMinute(1)->by('otp-send:'.$identifier.'|'.$request->ip()),
                Limit::perHour(5)->by('otp-send-identifier:'.$identifier),
                Limit::perHour(20)->by('otp-send-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('auth-otp-verify', function (Request $request): array {
            $identifier = strtolower(trim((string) ($request->input('email') ?: $request->input('identifier'))));

            return [
                Limit::perMinute(10)->by('otp-verify:'.$identifier.'|'.$request->ip()),
                Limit::perHour(30)->by('otp-verify-identifier:'.$identifier),
                Limit::perHour(120)->by('otp-verify-ip:'.$request->ip()),
            ];
        });

        Report::observe(ReportObserver::class);
        Complaint::observe(ComplaintObserver::class);
        Booking::observe(BookingObserver::class);
        SlotLock::observe(SlotLockObserver::class);
    }
}
