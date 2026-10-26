<?php

namespace Inertia;

use Spark\Foundation\Providers\ServiceProvider;
use Spark\Http\Request;
use Spark\Http\Routing\Route;
use Spark\Http\Routing\Router;
use Spark\View\Blade;
use function sprintf;

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
        $inertia = null;

        // Keep application configuration while replacing request-specific state
        // when a test runner or persistent host dispatches another request.
        $this->app->bind(Inertia::class, function (Request $request) use (&$inertia): Inertia {
            $inertia ??= new Inertia($request);
            $inertia->setRequest($request);

            return $inertia;
        });

        $this->app->make(Router::class)
            ->macro(
                'inertia',
                fn(string $path, string $component, array $props = []) => new Route($path, callback: fn() => inertia($component, $props))
            );
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
                fn(string $expression = '') => sprintf(
                    '<?= \Inertia\Facades\Inertia::renderRootElement($page ?? \'{}\'%s); ?>',
                    $expression === '' ? '' : ", {$expression}"
                )
            );
    }
}
