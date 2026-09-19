<?php

namespace Wnikk\LaravelAccessUi;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AccessUiServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/accessUi.php', 'accessUi');

        // One instance per request: it normalises the entity configuration once, which costs a few
        // queries against the owner-type list, and every controller asks it the same questions.
        $this->app->singleton(AccessUi::class, static function () {
            return new AccessUi;
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'accessUi');

        $this->registerRoutes();
        $this->registerBladeDirectives();
        $this->registerPublishing();
    }

    /**
     * Register the route group, if the configuration says it is safe to.
     *
     * The check is in {@see AccessUi::routesEnabled()}: both a prefix and a middleware stack have to
     * be named. An install that has not been configured therefore exposes nothing — these endpoints
     * hand out permissions, and an unguarded one is an open door to everything else.
     *
     * @return void
     */
    protected function registerRoutes()
    {
        /** @var AccessUi $ui */
        $ui = $this->app->make(AccessUi::class);

        if (!$ui->routesEnabled()) {
            return;
        }

        $attributes = [
            'prefix'     => $ui->routePrefix(),
            'middleware' => $ui->routeMiddleware(),
            'as'         => $ui->routeName(),
        ];

        $domain = $ui->config('routes.domain');

        if (is_string($domain) && $domain !== '') {
            $attributes['domain'] = $domain;
        }

        Route::group($attributes, function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/access-ui.php');
        });
    }

    /**
     * Two directives, so putting the assignment widget on a page is one line.
     *
     * The widget's whole point is that a host page — a user profile, say — should not have to know
     * the endpoints, the CSRF handling or the mount sequence. `@accessUiAssets` emits the bundle
     * tags, and `@accessUiWidget` emits the card and its init call.
     *
     * Both render nothing when the routes are not registered, so a page carrying them stays valid in
     * an installation where the panel is switched off.
     *
     * @return void
     */
    protected function registerBladeDirectives()
    {
        Blade::directive('accessUiAssets', static function () {
            return "<?php echo view('accessUi::assets')->render(); ?>";
        });

        Blade::directive('accessUiWidget', static function ($expression) {
            $expression = trim((string) $expression);
            $arguments  = $expression === '' ? '[]' : $expression;

            return "<?php echo view('accessUi::widget', ['options' => (array) ({$arguments})])->render(); ?>";
        });
    }

    /**
     * What a host application may take a copy of.
     *
     * @return void
     */
    protected function registerPublishing()
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/accessUi.php' => config_path('accessUi.php'),
        ], 'accessUi-config');

        // The bundle. Required: without it the panel page loads and mounts nothing.
        $this->publishes([
            __DIR__.'/../dist' => public_path('vendor/accessui'),
        ], 'accessUi-assets');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/accessUi'),
        ], 'accessUi-views');
    }
}
