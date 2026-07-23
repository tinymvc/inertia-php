<?php

namespace Inertia\Props;

use Inertia\Contracts\PropsContract;
use Spark\Contracts\Support\Arrayable;
use function is_object;

/**
 * The BaseProp class serves as a foundational implementation of the PropsContract, providing a way to create lazy-evaluated properties.
 * It allows you to define a property using a callback that will be evaluated when the property is
 * accessed, enabling deferred computation and efficient data handling in Inertia.js applications.
 * 
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
abstract class BaseProp implements PropsContract, \Stringable, Arrayable
{
    /**
     * Create a new base prop instance.
     *
     * @param mixed $value The prop value or an invokable object/closure.
     */
    public function __construct(protected mixed $value)
    {
    }

    /**
     * Evaluate the lazy prop and return its value.
     *
     * @return mixed The resolved value from the callback.
     */
    public function resolve(): mixed
    {
        return is_object($this->value) && is_callable($this->value)
            ? call($this->value)
            : $this->value;
    }

    /**
     * Allow the lazy prop to be invoked as a function, returning its resolved value.
     *
     * @return mixed The resolved value from the callback.
     */
    public function __invoke(): mixed
    {
        return $this->resolve();
    }

    /**
     * Convert the lazy prop to a string.
     *
     * @return string The resolved value as a string.
     */
    public function __toString(): string
    {
        return (string) $this->resolve();
    }

    /**
     * Convert the lazy prop to an array.
     *
     * @return array The resolved value as an array.
     */
    public function toArray(): array
    {
        return (array) $this->resolve();
    }
}
