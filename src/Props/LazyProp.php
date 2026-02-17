<?php

namespace Inertia\Props;

/**
 * LazyProp
 *
 * Lazy props are evaluated and sent to the client only when they are accessed on the client side. This allows you to defer
 * the computation of expensive props until they are actually needed, 
 * improving performance and reducing initial response size.
 *
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class LazyProp extends BaseProp
{
    /**
     * Whether this lazy prop should be cached using once behavior.
     *
     * @var bool
     */
    protected bool $once = false;

    /**
     * Mark this lazy prop to be cached (once behavior).
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
}
