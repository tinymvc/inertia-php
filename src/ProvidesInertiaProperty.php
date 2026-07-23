<?php

namespace Inertia;

interface ProvidesInertiaProperty
{
    public function toInertiaProperty(PropertyContext $context): mixed;
}
