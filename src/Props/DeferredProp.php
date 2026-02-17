<?php

namespace Inertia\Props;

use Closure;

/**
 * DeferredProp
 *
 * Deferred props are not included in the initial page response but are loaded in a subsequent request after the 
 * page has been rendered. This is useful for expensive computations that should 
 * not block the initial page load.
 *
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 * @link https://inertiajs.com/docs/v2/data-props/deferred-props
 */
class DeferredProp extends BaseProp
{
    /**
     * Whether this deferred prop should be cached using once behavior.
     *
     * @var bool
     */
    protected bool $once = false;

    /**
     * Merge configuration for this deferred prop.
     *
     * @var array|null
     */
    protected ?array $mergeConfig = null;

    /**
     * Create a new deferred prop instance.
     *
     * @param Closure $callback The callback that returns the prop value when evaluated.
     * @param string $group The group name for batching deferred props (default: 'default').
     */
    public function __construct(
        protected Closure $callback,
        protected string $group = 'default'
    ) {
    }

    /**
     * Get the group name for this deferred prop.
     *
     * @return string The group name.
     */
    public function getGroup(): string
    {
        return $this->group;
    }

    /**
     * Mark this deferred prop to be cached (once behavior).
     *
     * @return static
     */
    public function once(): static
    {
        $this->once = true;
        return $this;
    }

    /**
     * Check if this prop has once behavior.
     *
     * @return bool True if should be cached.
     */
    public function isOnce(): bool
    {
        return $this->once;
    }

    /**
     * Mark this deferred prop to use merge (append) behavior when loaded.
     *
     * @return static
     */
    public function merge(): static
    {
        $this->mergeConfig = ['strategy' => MergeProp::MERGE_APPEND];
        return $this;
    }

    /**
     * Mark this deferred prop to use deep merge behavior when loaded.
     *
     * @return static
     */
    public function deepMerge(): static
    {
        $this->mergeConfig = ['strategy' => MergeProp::MERGE_DEEP];
        return $this;
    }

    /**
     * Get merge configuration.
     *
     * @return array|null The merge config.
     */
    public function getMergeConfig(): ?array
    {
        return $this->mergeConfig;
    }
}
