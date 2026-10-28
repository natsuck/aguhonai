<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
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
        Password::defaults(fn () => Password::min(12)->mixedCase()->numbers()->symbols());

        RateLimiter::for('quiz-generation', function (Request $request): array {
            return [
                Limit::perMinutes(10, 5)->by('quiz-generation-user:'.$request->user()->getAuthIdentifier()),
                Limit::perMinutes(10, 20)->by('quiz-generation-ip:'.$request->ip()),
            ];
        });
    }
}
