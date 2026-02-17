<?php

namespace Inertia\Facades;

use Spark\Facades\Facade;
use Inertia\Inertia as BaseInertia;

/**
 * Facade Props
 * 
 * This class serves as a facade for the Inertia Props, providing a static interface to the underlying Props class.
 * It allows easy access to Inertia Props methods without needing to instantiate the Props class directly.
 * 
 * @method static void share(array $data)
 * @method static void composer(string|array $components, callable $composer)
 * @method static \Inertia\Props\LazyProp lazy(\Closure $callback)
 * @method static \Inertia\Props\LazyProp optional(\Closure $callback)
 * @method static \Inertia\Props\DeferredProp defer(\Closure $callback, string $group = 'default')
 * @method static \Inertia\Props\MergeProp merge(\Closure $callback, ?string $matchBy = null)
 * @method static \Inertia\Props\MergeProp prepend(\Closure $callback, ?string $matchBy = null)
 * @method static \Inertia\Props\MergeProp deepMerge(\Closure $callback, ?string $matchBy = null)
 * @method static \Inertia\Props\OnceProp once(\Closure $callback, ?string $key = null, ?int $expiresAt = null)
 * @method static \Inertia\Props\OnceProp shareOnce(string $key, Closure $callback)
 * @method static \Inertia\Props\AlwaysProp always(\Closure $callback)
 * @method static void flushShared()
 * 
 * @package Inertia\Facades
 * 
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class Props extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return BaseInertia::class;
    }
}