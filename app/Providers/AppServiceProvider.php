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

        $renderExternal = getenv('RENDER_EXTERNAL_URL') ?: null;
        $onRender = PublicHttps::isRenderRuntime(getenv('RENDER') ?: null, $renderExternal);
        $requestHttps = PublicHttps::requestIsHttps(
            request()->header('X-Forwarded-Proto'),
            request()->secure(),
        );

        if (! PublicHttps::shouldForce(
            config('app.url'),
            config('app.asset_url'),
            $renderExternal,
            $requestHttps,
            $onRender,
        )) {
            return;
        }

        $root = PublicHttps::publicOrigin(config('app.url'), $renderExternal);
        $assets = PublicHttps::toHttps(config('app.asset_url') ?: $root);

        URL::forceScheme('https');

        if (is_string($root) && $root !== '') {
            URL::forceRootUrl(rtrim($root, '/'));
            config(['app.url' => $root]);
        }

        if (is_string($assets) && $assets !== '') {
            $assets = rtrim($assets, '/');
            config(['app.asset_url' => $assets]);
            app('url')->useAssetOrigin($assets);
        }
    }
}
