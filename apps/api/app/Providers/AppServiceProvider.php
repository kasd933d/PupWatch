<?php

namespace App\Providers;

use App\Services\Dns\DnsResolver;
use App\Services\Dns\SystemDnsResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Tests swap this binding for a fake resolver (PRD §14).
        $this->app->singleton(DnsResolver::class, SystemDnsResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerRateLimiters();
    }

    private function registerRateLimiters(): void
    {
        // PRD §10: auth endpoints 5/min per IP + email.
        RateLimiter::for('auth', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        // PRD §10: 60 req/min per user for the authenticated API.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: 'ip:'.$request->ip());
        });

        // PRD §10: 30 req/min per IP for public endpoints (status pages, /up).
        RateLimiter::for('public', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
    }
}
