<?php

declare(strict_types=1);

namespace Wnikk\LaravelAccessUi;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AccessUiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/accessUi.php', 'accessUi');

        // One instance per request: it normalises the entity configuration once, and every
        // controller asks it the same questions. A singleton would keep the answer of the
        // first request of an Octane worker for every request after it.
        $this->app->scoped(AccessUi::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'accessUi');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'accessUi');

        $this->registerRoutes();
        $this->registerBladeDirectives();
        $this->registerPublishing();
    }

    /**
     * The route group, when the configuration says it is safe to register one. AccessUi::routesEnabled()
     * wants both a prefix and a middleware stack: an install that was not configured exposes nothing.
     */
    protected function registerRoutes(): void
    {
        $ui = $this->app->make(AccessUi::class);

        if (! $ui->routesEnabled()) {
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

        Route::group($attributes, fn () => $this->loadRoutesFrom(__DIR__.'/../routes/access-ui.php'));
    }

    /**
     * Two directives, so putting the assignment widget on a page is one line: @accessUiAssets emits
     * the bundle tags, @accessUiWidget the card and its mount call. Both render nothing while the
     * routes are not registered, so a page carrying them stays valid with the panel switched off.
     */
    protected function registerBladeDirectives(): void
    {
        Blade::directive('accessUiAssets', static fn (): string => "<?php echo view('accessUi::assets')->render(); ?>");

        Blade::directive('accessUiWidget', static function (?string $expression): string {
            $arguments = trim((string) $expression) ?: '[]';

            return "<?php echo view('accessUi::widget', ['options' => (array) ({$arguments})])->render(); ?>";
        });
    }

    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([__DIR__.'/../config/accessUi.php' => config_path('accessUi.php')], 'accessUi-config');

        // The bundle. Required: without it the panel page loads and mounts nothing.
        $this->publishes([__DIR__.'/../dist' => public_path('vendor/accessui')], 'accessUi-assets');

        $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/accessUi')], 'accessUi-views');
        $this->publishes([__DIR__.'/../resources/lang' => $this->app->langPath('vendor/accessUi')], 'accessUi-lang');
    }
}
