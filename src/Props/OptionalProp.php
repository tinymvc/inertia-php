<?php

namespace Inertia\Props;

use Inertia\Props\Concerns\InteractsWithOnce;

/**
 * A prop that is omitted from normal visits and only resolved when explicitly
 * requested by a partial reload.
 */
class OptionalProp extends BaseProp
{
    use InteractsWithOnce;
}
