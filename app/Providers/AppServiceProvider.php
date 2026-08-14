<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\AuditAction;
use App\Models\User;
use App\Policies\AnalyticsPolicy;
use App\Services\AuditLogger;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
        // AnalyticsPolicy has no backing model, so auto-discovery cannot find
        // it; register it explicitly to enable class-level authorization.
        Gate::policy(AnalyticsPolicy::class, AnalyticsPolicy::class);

        // Rate-limit login attempts per email + IP (AUTH-2) to slow brute-force.
        RateLimiter::for('login', function (Request $request) {
            $key = mb_strtolower((string) $request->input('email', '')).'|'.$request->ip();

            return Limit::perMinute(5)->by($key)->response(function (Request $request) {
                $user = User::where('email', $request->input('email'))->first();
                $this->app->make(AuditLogger::class)->log($user, AuditAction::LOGIN_THROTTLED, $user);

                return response()->json([
                    'success' => false,
                    'message' => 'Terlalu banyak permintaan. Silakan coba lagi setelah batas waktu.',
                    'data' => null,
                    'errors' => ['throttle' => ['Terlalu banyak permintaan. Silakan coba lagi setelah batas waktu.']],
                ], 429);
            });
        });

        Model::preventLazyLoading(! app()->isProduction());
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());
        Model::preventAccessingMissingAttributes(! app()->isProduction());
    }
}
