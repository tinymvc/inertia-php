<?php

namespace Inertia\Props;

use Inertia\Props\Concerns\InteractsWithMerges;
use Inertia\Props\Concerns\InteractsWithOnce;

/**
 * A prop loaded by the client after the initial page has rendered.
 */
class DeferredProp extends BaseProp
{
    use InteractsWithMerges;
    use InteractsWithOnce;

    public function __construct(
        callable $callback,
        protected string $group = 'default',
        protected bool $rescue = false
    ) {
        parent::__construct($callback);
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    public function shouldRescue(): bool
    {
        return $this->rescue;
    }
}
