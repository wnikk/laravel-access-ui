<?php

declare(strict_types=1);

namespace Wnikk\LaravelAccessUi;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;

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
     *
     * The directives are added when the Blade compiler is first resolved, not at boot: the Blade facade
     * would build the compiler right away, and the compiler refuses to exist without a view cache path.
     * An application whose storage/framework/views is missing would then fail to boot at all, in every
     * artisan command, with the panel named in the trace. Now it boots, and the first rendered view
     * reports the missing directory, as it does without the panel.
     */
    protected function registerBladeDirectives(): void
    {
        $this->callAfterResolving('blade.compiler', static function (BladeCompiler $blade): void {
            $blade->directive('accessUiAssets', static fn (): string => "<?php echo view('accessUi::assets')->render(); ?>");

            $blade->directive('accessUiWidget', static function (?string $expression): string {
                $arguments = trim((string) $expression) ?: '[]';

                return "<?php echo view('accessUi::widget', ['options' => (array) ({$arguments})])->render(); ?>";
            });
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
