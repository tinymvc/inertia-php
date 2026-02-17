<?php

namespace Inertia;

use Spark\Foundation\Providers\ServiceProvider;
use Spark\Routing\Route;
use Spark\Routing\Router;
use Spark\View\Blade;

/**
 * The Inertia service provider.
 * 
 * This service provider registers the Inertia singleton and adds the `@inertia`
 * Blade directive and the `inertia` router macro.
 * 
 * @see https://inertiajs.com/server-side-setup
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class InertiaServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(Inertia::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->app->make(Blade::class)
            ->directive(
                'inertia',
                fn() => '<?= \Inertia\Facades\Inertia::renderRootElement($page ?? \'{}\'); ?>'
            );

        $this->app->make(Router::class)
            ->macro(
                'inertia',
                fn(string $path, string $component, array $props = []) => new Route($path, callback: fn() => inertia($component, $props))
            );
    }
}