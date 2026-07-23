<?php

namespace Inertia\Props;

use Inertia\Props\Concerns\InteractsWithMerges;
use Inertia\Props\Concerns\InteractsWithOnce;

/**
 * A prop whose value is merged with the existing client-side value during a
 * partial reload.
 */
class MergeProp extends BaseProp
{
    use InteractsWithMerges;
    use InteractsWithOnce;

    public function __construct(mixed $value)
    {
        parent::__construct($value);
        $this->merge = true;
    }
}
