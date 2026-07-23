<?php

namespace Inertia;

interface ProvidesInertiaProperties
{
    /**
     * @return iterable<string, mixed>
     */
    public function toInertiaProperties(RenderContext $context): iterable;
}
