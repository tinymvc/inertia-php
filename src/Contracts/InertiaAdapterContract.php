<?php

namespace Inertia\Contracts;

use BackedEnum;
use Inertia\Props\AlwaysProp;
use Inertia\Props\DeferredProp;
use Inertia\Props\MergeProp;
use Inertia\Props\OnceProp;
use Inertia\Props\OptionalProp;
use Inertia\Props\ScrollProp;
use Inertia\ProvidesInertiaProperties;
use Spark\Contracts\Support\Arrayable;
use Spark\Http\Response;
use UnitEnum;

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
     * @param BackedEnum|UnitEnum|string $component The Inertia page component.
     * @param Arrayable|ProvidesInertiaProperties|array $props Page props.
     */
    public function render(
        BackedEnum|UnitEnum|string $component,
        Arrayable|ProvidesInertiaProperties|array $props = []
    ): Response;

    /**
     * Redirect to a given URL.
     *
     * @param string $url The URL to redirect to.
     * @param int $status The HTTP status code for the redirection (default is 302).
     * @return Response A response object representing the redirection.
     */
    public function redirect(string $url, int $status = 302): Response;

    /**
     * Create a prop that is only evaluated when explicitly requested.
     */
    public static function optional(callable $callback): OptionalProp;

    /**
     * Create a deferred prop that is loaded after the initial page render.
     *
     * @param callable $callback The callback that returns the prop value.
     * @param string $group The group name for batching deferred props.
     * @param bool $rescue Whether resolution exceptions should be rescued.
     * @return DeferredProp The deferred prop instance.
     */
    public static function defer(
        callable $callback,
        string $group = 'default',
        bool $rescue = false
    ): DeferredProp;

    /**
     * Create a merge prop that appends data to existing client-side data.
     *
     * @param mixed $value The prop value or lazy callback.
     * @return MergeProp The merge prop instance.
     */
    public static function merge(mixed $value): MergeProp;

    /**
     * Create a once prop that is only evaluated and sent once.
     *
     * @param callable $callback The callback that returns the prop value.
     * @return OnceProp The once prop instance.
     */
    public static function once(callable $callback): OnceProp;

    /**
     * Create an always prop that is always included even in partial reloads.
     *
     * @param mixed $value The prop value or lazy callback.
     * @return AlwaysProp The always prop instance.
     */
    public static function always(mixed $value): AlwaysProp;

    public static function scroll(
        mixed $value,
        string $wrapper = 'data',
        ProvidesScrollMetadata|callable|null $metadata = null
    ): ScrollProp;

    /**
     * Share data across all Inertia responses.
     *
     * @param array|string|Arrayable|ProvidesInertiaProperties $key Shared props or a key.
     */
    public static function share(
        array|string|Arrayable|ProvidesInertiaProperties $key,
        mixed $value = null
    ): void;
}
