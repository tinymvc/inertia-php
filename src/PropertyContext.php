<?php

namespace Inertia;

use Spark\Http\Request;

class PropertyContext
{
    public function __construct(
        public string $key,
        public array $props,
        public Request $request
    ) {
    }
}
