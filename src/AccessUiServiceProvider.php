<?php

namespace Wnikk\LaravelAccessUi;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
class AccessUiServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerRoutes();
        $this->registerResources();
        $this->defineAssetPublishing();
    }

    /**
     * Register the routes used by the Laratrust admin panel.
     *
     * @return void
     */
    protected function registerRoutes()
    {
        if (!$this->app['config']->get('accessUi.register')) {
            return;
        }

        Route::group([
            'domain' => config('accessUi.domain', (app()->runningInConsole() === false) ? request()->getHost() : 'localhost'),
            'prefix' => config('accessUi.path'),
            'namespace' => 'Wnikk\LaravelAccessUi\Http\Controllers',
            'middleware' => config('accessUi.middleware', 'web'),
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
    }

    /**
     * Register all the possible views
     *
     * @return void
     */
    protected function registerResources()
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'accessUi');
    }

    /**
     * Register the assets that are publishable for the admin panel to work.
     *
     * @return void
     */
    protected function defineAssetPublishing()
    {
        if (!$this->app['config']->get('accessUi.register')) {
            return;
        }

        $this->publishes([
            __DIR__.'/../dist' => public_path('vendor/accessui'),
        ], 'accessui-assets');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->configure();
        $this->offerPublishing();
    }

    /**
     * Setup the configuration.
     *
     * @return void
     */
    protected function configure()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/accessUi.php', 'accessUi');
    }

    /**
     * Setup the resource publishing group for Laratrust.
     *
     * @return void
     */
    protected function offerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/accessUi.php' => config_path('accessUi.php'),
            ], 'accessUi-config');

            $this->publishes([
                __DIR__. '/../resources/views' => resource_path('views/vendor/accessUi'),
            ], 'accessUi-views');
        }
    }
}
