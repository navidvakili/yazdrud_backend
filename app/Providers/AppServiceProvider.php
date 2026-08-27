<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Illuminate\Support\Facades\Schema;

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
        Schema::defaultStringLength(191);
        // Passport token expiration settings.
        // The admin panel only ever issues personal-access tokens (see
        // AuthController::login) — tokensExpireIn/refreshTokensExpireIn govern
        // OAuth grants this app doesn't use, left at their defaults for
        // completeness. personalAccessTokensExpireIn is the one that matters:
        // an absolute 8h cap, paired with EnsureTokenNotIdle's 30-minute
        // idle timeout, bounds how long a stolen token stays useful without
        // needing a refresh-token flow.
        Passport::tokensExpireIn(now()->addDays(15));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::personalAccessTokensExpireIn(now()->addHours(8));
    }
}
