<?php

namespace Inertia\Contracts;

use Closure;
use Inertia\Props\AlwaysProp;
use Inertia\Props\DeferredProp;
use Inertia\Props\LazyProp;
use Inertia\Props\MergeProp;
use Inertia\Props\OnceProp;
use Spark\Http\Response;

/**
 * Interface InertiaAdapterContract
 * 
 * This interface defines the contract for an adapter that integrates Inertia.js with the Spark framework. It provides methods for rendering Inertia.js components 
 * and handling redirections in a way that is compatible with Inertia.js's expectations.
 * 
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
interface InertiaAdapterContract
{
    /**
     * Render an Inertia.js component.
     *
     * @param string $component The name of the Inertia.js component to render.
     * @param array $props An associative array of props to pass to the component.
     * @return mixed The rendered component, which can be a Response or any other type depending on the implementation.
     */
    public function render(string $component, array $props = []): mixed;

    /**
     * Redirect to a given URL.
     *
     * @param string $url The URL to redirect to.
     * @param int $status The HTTP status code for the redirection (default is 302).
     * @return Response A response object representing the redirection.
     */
    public function redirect(string $url, int $status = 302): Response;

    /**
     * Create a lazy prop that is only evaluated during partial reloads.
     *
     * @param Closure $callback The callback that returns the prop value.
     * @return LazyProp The lazy prop instance.
     */
    public static function lazy(Closure $callback): LazyProp;

    /**
     * Create a deferred prop that is loaded after the initial page render.
     *
     * @param Closure $callback The callback that returns the prop value.
     * @param string $group The group name for batching deferred props.
     * @return DeferredProp The deferred prop instance.
     */
    public static function defer(Closure $callback, string $group = 'default'): DeferredProp;

    /**
     * Create a merge prop that appends data to existing client-side data.
     *
     * @param Closure $callback The callback that returns the prop value.
     * @param string|null $matchBy Optional key path for matching items during merge.
     * @return MergeProp The merge prop instance.
     */
    public static function merge(Closure $callback, ?string $matchBy = null): MergeProp;

    /**
     * Create a once prop that is only evaluated and sent once.
     *
     * @param Closure $callback The callback that returns the prop value.
     * @param string|null $key Optional custom key for tracking across pages.
     * @param int|null $expiresAt Optional expiration timestamp in milliseconds.
     * @return OnceProp The once prop instance.
     */
    public static function once(Closure $callback, ?string $key = null, ?int $expiresAt = null): OnceProp;

    /**
     * Create an always prop that is always included even in partial reloads.
     *
     * @param Closure $callback The callback that returns the prop value.
     * @return AlwaysProp The always prop instance.
     */
    public static function always(Closure $callback): AlwaysProp;

    /**
     * Share data across all Inertia responses.
     *
     * @param array $data An associative array of data to share across all Inertia responses.
     * @return void
     */
    public static function share(array $data): void;
}