<?php

namespace Inertia;

use Spark\Http\Request;

class RenderContext
{
    public function __construct(
        public string $component,
        public Request $request
    ) {
    }
}
