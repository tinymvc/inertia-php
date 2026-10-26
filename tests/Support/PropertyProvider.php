<?php

namespace Tests\Support;

use Closure;
use Inertia\{ProvidesInertiaProperties, RenderContext};

final class PropertyProvider implements ProvidesInertiaProperties
{
    public function __construct(private Closure $callback)
    {
    }

    public function toInertiaProperties(RenderContext $context): iterable
    {
        return ($this->callback)($context);
    }
}
