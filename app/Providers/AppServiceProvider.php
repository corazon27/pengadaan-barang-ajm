<?php

declare(strict_types=1);

namespace App\Providers;

use App\Policies\AnalyticsPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
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
        // AnalyticsPolicy has no backing model, so auto-discovery cannot find
        // it; register it explicitly to enable class-level authorization.
        Gate::policy(AnalyticsPolicy::class, AnalyticsPolicy::class);

        Model::preventLazyLoading(! app()->isProduction());
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());
        Model::preventAccessingMissingAttributes(! app()->isProduction());
    }
}
