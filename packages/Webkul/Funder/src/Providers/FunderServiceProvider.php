<?php

namespace Webkul\Funder\Providers;

use Illuminate\Support\ServiceProvider;
use Webkul\Funder\Adapters\FunderAdapter;
use Webkul\Funder\Services\SubmitToFunders;

class FunderServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadRoutesFrom(__DIR__.'/../Routes/admin.php');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'funder');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'funder');

        $this->app->register(ModuleServiceProvider::class);
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->registerConfig();

        $this->app->singleton(FunderAdapter::class);

        $this->app->singleton(SubmitToFunders::class);
    }

    /**
     * Register package config.
     */
    protected function registerConfig(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/menu.php', 'menu.admin');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/acl.php', 'acl');
    }
}
