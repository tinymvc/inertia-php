<?php

namespace Inertia\Contracts;

/**
 * Interface PropsContract
 *
 * This contract defines the basic structure for props that can be resolved to a value.
 */
interface PropsContract
{
    /**
     * Resolve the prop to its value.
     *
     * @return mixed The resolved value of the prop.
     */
    public function resolve(): mixed;
}
