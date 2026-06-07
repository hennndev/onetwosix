<?php

namespace App\Providers;

use App\Models\DisplayMessageRequest;
use App\Models\SongRequest;
use App\Models\TableReservation;
use App\Services\AccurateService;
use App\Support\RealtimeTopSpenderBanner;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AccurateService::class, function ($app) {
            return new AccurateService;
        });

        $this->app->singleton(RealtimeTopSpenderBanner::class, function ($app) {
            return new RealtimeTopSpenderBanner;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.sidebar', function ($view) {
            $view->with('pendingBookingsCount', TableReservation::where('status', ['confirmed', 'pending'])->count());
            $view->with('pendingSongRequestsCount', SongRequest::where('status', 'pending')->count());
            $view->with('pendingDisplayMessagesCount', DisplayMessageRequest::where('status', 'pending')->count());
        });

        View::composer('layouts.top-spender-banner', function ($view) {
            $view->with('realtimeTopSpender', app(RealtimeTopSpenderBanner::class)->current());
        });
    }
}
