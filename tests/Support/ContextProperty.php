<?php

namespace Tests\Support;

use Closure;
use Inertia\{PropertyContext, ProvidesInertiaProperty};

final class ContextProperty implements ProvidesInertiaProperty
{
    public function __construct(private Closure $callback)
    {
    }

    public function toInertiaProperty(PropertyContext $context): mixed
    {
        return ($this->callback)($context);
    }
}
