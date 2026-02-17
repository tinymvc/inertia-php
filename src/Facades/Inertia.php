<?php

namespace Spark\Facades;

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
 * @method static void setVersion(string $version)
 * @method static void share(array $data)
 * @method static void composer(string|array $components, callable $composer)
 * @method static \Spark\Http\Response render(string $component, \Spark\Contracts\Support\Arrayable|array $props = [])
 * @method static \Spark\Http\Response redirect(string $url, int $status = 302)
 * @method static \Spark\Http\Response back(int $status = 302)
 * @method static \Spark\Http\Response location(string $url)
 * @method static \Spark\Http\Response forceRefresh()
 * @method static \Inertia\Props\LazyProp lazy(\Closure $callback)
 * @method static \Inertia\Props\LazyProp optional(\Closure $callback)
 * @method static \Inertia\Props\DeferredProp defer(\Closure $callback, string $group = 'default')
 * @method static \Inertia\Props\MergeProp merge(\Closure $callback, ?string $matchBy = null)
 * @method static \Inertia\Props\MergeProp prepend(\Closure $callback, ?string $matchBy = null)
 * @method static \Inertia\Props\MergeProp deepMerge(\Closure $callback, ?string $matchBy = null)
 * @method static \Inertia\Props\OnceProp once(\Closure $callback, ?string $key = null, ?int $expiresAt = null)
 * @method static \Inertia\Props\OnceProp shareOnce(string $key, Closure $callback)
 * @method static \Inertia\Props\AlwaysProp always(\Closure $callback)
 * @method static \Inertia\Inertia withEncryptedHistory(bool $encrypt = true)
 * @method static \Inertia\Inertia withClearedHistory(bool $clear = true)
 * @method static \Spark\Contracts\Support\Htmlable renderRootElement(string|array $page = '{}')
 * @method static mixed getShared(?string $key = null, mixed $default = null)
 * @method static void flushShared()
 * 
 * @package Spark\Facades
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