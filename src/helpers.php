<?php

use Spark\Contracts\Support\Arrayable;
use Spark\Http\Response;
use Inertia\Inertia;

if (!function_exists('inertia')) {
    /**
     * Create an Inertia response.
     *
     * @param null|string $component The component name to render
     * @param Arrayable|array $props The props to pass to the component
     * @return ($component is null ? Inertia : Response) The Inertia instance or the response with the rendered component
     */
    function inertia(null|string $component = null, Arrayable|array $props = []): Inertia|Response
    {
        /** @var \Inertia\Inertia $inertia The Inertia adapter instance */
        $inertia = get(Inertia::class);

        if ($component === null) {
            return $inertia;
        }

        return $inertia->render($component, $props);
    }
}