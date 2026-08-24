<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
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
        $this->configureTrustedProxies();

        // Super Admin Bypass: Izinkan semua akses jika role-nya 'super-admin'
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super-admin') ? true : null;
        });

        RateLimiter::for('membership-intake', function (Request $request) {
            return Limit::perMinute(6)->by($request->ip());
        });

        RateLimiter::for('capability-redeem', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('capability-revision', function (Request $request) {
            $grantId = $request->session()->get((string) config('secure_capability.session_key', 'capability_grant_id'));

            return Limit::perMinute(20)->by($grantId ? 'grant:'.$grantId : $request->ip());
        });
    }

    private function configureTrustedProxies(): void
    {
        $trustedProxies = config('app.trusted_proxies');

        if (is_string($trustedProxies) && trim($trustedProxies) !== '') {
            TrustProxies::at($trustedProxies);

            return;
        }

        if (is_array($trustedProxies) && $trustedProxies !== []) {
            TrustProxies::at($trustedProxies);
        }
    }
}
