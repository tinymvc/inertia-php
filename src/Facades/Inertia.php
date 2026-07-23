<?php

namespace Inertia\Facades;

use Spark\Facades\Facade;
use Inertia\Inertia as BaseInertia;

/**
 * Facade Inertia
 * 
 * This class serves as a facade for the Inertia view adapter, providing a static interface to the underlying Inertia class.
 * It allows easy access to Inertia rendering methods without needing to instantiate the Inertia class directly.
 * 
 * @method static \Inertia\Inertia instance()
 * @method static void setRootView(string $view)
 * @method static void setRootElementId(string $id)
 * @method static void setBuildDirectory(string $build)
 * @method static void setVersion(\Closure|string|int|null $version)
 * @method static void version(\Closure|string|int|null $version)
 * @method static string getVersion()
 * @method static void share(array|string|\Spark\Contracts\Support\Arrayable|\Inertia\ProvidesInertiaProperties $key, mixed $value = null)
 * @method static void composer(string|array $components, callable $composer)
 * @method static \Spark\Http\Response render(\BackedEnum|\UnitEnum|string $component, \Spark\Contracts\Support\Arrayable|\Inertia\ProvidesInertiaProperties|array $props = [])
 * @method static \Spark\Http\Response redirect(string $url, int $status = 302)
 * @method static \Spark\Http\Response back(int $status = 302)
 * @method static \Spark\Http\Response location(string $url)
 * @method static \Spark\Http\Response forceRefresh()
 * @method static \Inertia\Props\OptionalProp optional(callable $callback)
 * @method static \Inertia\Props\DeferredProp defer(callable $callback, string $group = 'default', bool $rescue = false)
 * @method static \Inertia\Props\MergeProp merge(mixed $value)
 * @method static \Inertia\Props\MergeProp prepend(mixed $value, ?string $matchOn = null)
 * @method static \Inertia\Props\MergeProp deepMerge(mixed $value, string|array|null $matchOn = null)
 * @method static \Inertia\Props\OnceProp once(callable $callback)
 * @method static \Inertia\Props\OnceProp shareOnce(string $key, callable $callback)
 * @method static \Inertia\Props\AlwaysProp always(mixed $value)
 * @method static \Inertia\Props\ScrollProp scroll(mixed $value, string $wrapper = 'data', \Inertia\Contracts\ProvidesScrollMetadata|callable|null $metadata = null)
 * @method static \Inertia\Inertia flash(\BackedEnum|\UnitEnum|string|array $key, mixed $value = null)
 * @method static \Inertia\Inertia withEncryptedHistory(bool $encrypt = true)
 * @method static \Inertia\Inertia withClearedHistory(bool $clear = true)
 * @method static \Inertia\Inertia encryptHistory(bool $encrypt = true)
 * @method static \Inertia\Inertia clearHistory(bool $clear = true)
 * @method static \Inertia\Inertia preserveFragment(bool $preserve = true)
 * @method static \Spark\Contracts\Support\Htmlable renderRootElement(string|array $page = '{}', ?string $id = null)
 * @method static mixed getShared(?string $key = null, mixed $default = null)
 * @method static void flushShared()
 * 
 * @package Inertia\Facades
 * 
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class Inertia extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return BaseInertia::class;
    }
}
