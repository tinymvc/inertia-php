<?php

namespace Inertia\Props;

use Inertia\Props\Concerns\InteractsWithOnce;

/**
 * A prop that the Inertia client remembers and reuses between page visits.
 */
class OnceProp extends BaseProp
{
    use InteractsWithOnce;

    public function __construct(callable $callback)
    {
        parent::__construct($callback);
        $this->once = true;
    }
}
