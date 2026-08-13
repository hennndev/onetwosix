<?php

namespace App\Providers;

use App\Models\DisplayMessageRequest;
use App\Models\SongRequest;
use App\Models\TableReservation;
use App\Models\User;
use App\Services\AccurateService;
use App\Support\RealtimeTopSpenderBanner;
use Illuminate\Support\Facades\Gate;
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
        Gate::define('viewApiDocs', fn (?User $user = null): bool => app()->isLocal()
            || app()->runningUnitTests()
            || $user?->hasRole('Administrator') === true);

        View::composer('layouts.sidebar', function ($view) {
            $areaId = $this->activeAreaId();

            $view->with('pendingBookingsCount', TableReservation::where('status', ['confirmed', 'pending'])
                ->when($areaId, fn ($q) => $q->whereHas('table.area', fn ($t) => $t->where('id', $areaId)))
                ->count());
            $view->with('pendingSongRequestsCount', SongRequest::where('status', 'pending')->count());
            $view->with('pendingDisplayMessagesCount', DisplayMessageRequest::where('status', 'pending')->count());
        });

        View::composer('layouts.top-spender-banner', function ($view) {
            $view->with('realtimeTopSpender', app(RealtimeTopSpenderBanner::class)->current($this->activeAreaId()));
        });
    }

    protected function activeAreaId(): ?int
    {
        $user = auth()->user();

        if ($user) {
            return $user->resolveActiveAreaId();
        }

        $sessionAreaId = session('active_area_id');

        return ($sessionAreaId && $sessionAreaId !== 'all') ? (int) $sessionAreaId : null;
    }
}
