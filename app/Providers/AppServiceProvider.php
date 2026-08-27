<?php

namespace App\Providers;

use App\Support\PublicHttps;
use Illuminate\Support\Facades\URL;
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
        if ($this->app->environment('testing')) {
            return;
        }

        $requestHttps = PublicHttps::requestIsHttps(
            request()->header('X-Forwarded-Proto'),
            request()->secure(),
        );

        if (! PublicHttps::shouldForce(
            config('app.url'),
            config('app.asset_url'),
            getenv('RENDER_EXTERNAL_URL') ?: null,
            $requestHttps,
        )) {
            return;
        }

        URL::forceScheme('https');

        $root = config('app.url');

        if (is_string($root) && str_starts_with($root, 'https://')) {
            URL::forceRootUrl(rtrim($root, '/'));
        }
    }
}
