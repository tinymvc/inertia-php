<?php

namespace Inertia;

use Closure;
use Spark\Contracts\Support\Arrayable;
use Spark\Foundation\Application;
use Inertia\Props\AlwaysProp;
use Inertia\Props\DeferredProp;
use Inertia\Props\LazyProp;
use Inertia\Props\MergeProp;
use Inertia\Props\OnceProp;
use Spark\Http\Request;
use Spark\Http\Response;
use Spark\Support\HtmlString;
use Inertia\Contracts\InertiaAdapterContract;
use function in_array;
use function is_array;
use function sprintf;

/**
 * Inertia
 *
 * This class implements the IntertiaAdapterContract to provide a way to render Inertia.js components in a PHP application.
 * It handles both AJAX requests (returning JSON) and regular requests (rendering a view with the component data).
 * The root view can be set using the setRootView method, which defaults to 'app'.
 * 
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class Inertia implements InertiaAdapterContract
{
    /**
     * The root view that will be used to render the Inertia component. This view should include the necessary
     * JavaScript and HTML structure to handle Inertia.js on the client side.
     *
     * @var string
     */
    protected string $rootView = 'app';

    /**
     * The version string used for cache busting. This can be set to a value or generated dynamically
     * based on the component and props to ensure that clients receive the latest version of the component.
     *
     * @var string
     */
    protected string $version = '1.0';

    /**
     * An array of shared data that will be included in every Inertia response. This can be used to share
     * common props across all components, such as user information or application settings.
     *
     * @var array
     */
    protected static array $shared = [];

    /**
     * An array of view composers that can be used to modify the data passed to the view before rendering.
     * This allows for dynamic data manipulation based on the component being rendered or other factors.
     *
     * @var array
     */
    protected static array $composers = [];

    /**
     * Whether to encrypt the current page's history state.
     * When enabled, the page data will be encrypted in the browser's history.
     *
     * @var bool
     */
    protected bool $encryptHistory = false;

    /**
     * Whether to clear any encrypted history state.
     * When enabled, previously encrypted history entries will be cleared.
     *
     * @var bool
     */
    protected bool $clearHistory = false;

    /**
     * Inertia constructor.
     *
     * @param Request $request The current request instance.
     */
    public function __construct(protected Request $request)
    {
        // Generate version based on manifest file content for cache busting
        $manifestPath = root_dir('public/build/.vite/manifest.json');
        if (is_file($manifestPath)) {
            $this->version = md5_file($manifestPath);
        }
    }

    /**
     * Get the Inertia instance from the application container.
     *
     * @return Inertia The Inertia instance.
     */
    public static function instance(): Inertia
    {
        return Application::$app->get(Inertia::class);
    }

    /**
     * Set the root view for rendering Inertia components.
     *
     * @param string $view The name of the view to use as the root for Inertia rendering.
     * @return void
     */
    public function setRootView(string $view): void
    {
        $this->rootView = $view;
    }

    /**
     * Set the version string for cache busting.
     *
     * @param string $version The version string to use for cache busting.
     * @return void
     */
    public function setVersion(string $version): void
    {
        $this->version = $version;
    }

    /**
     * Share data across all Inertia responses.
     *
     * This method allows you to add data that will be included in every Inertia response. This is useful for sharing
     * common props such as user information, application settings, or any other data that should be available to all components.
     *
     * @param array|string $key An associative array of data or a key name.
     * @param mixed $value The value when $key is a string.
     * @return void
     */
    public static function share(array|string $key, mixed $value = null): void
    {
        if (is_array($key)) {
            self::$shared = [...self::$shared, ...$key];
        } else {
            self::$shared[$key] = $value;
        }
    }

    /**
     * Get shared data by key, or all shared data if no key is provided.
     *
     * @param string|null $key The key to retrieve.
     * @param mixed $default The default value if key doesn't exist.
     * @return mixed The shared data.
     */
    public static function getShared(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return self::$shared;
        }

        return self::$shared[$key] ?? $default;
    }

    /**
     * Share a once prop across all Inertia responses.
     *
     * @param string $key The key name for the shared prop.
     * @param Closure $callback The callback that returns the prop value.
     * @return \Inertia\Props\OnceProp The once prop instance.
     */
    public static function shareOnce(string $key, Closure $callback): OnceProp
    {
        $onceProp = new OnceProp($callback);
        self::$shared[$key] = $onceProp;
        return $onceProp;
    }

    /**
     * Flush all shared data. Useful for testing.
     *
     * @return void
     */
    public static function flushShared(): void
    {
        self::$shared = [];
        self::$composers = [];
    }

    /**
     * Register a view composer for specific components.
     *
     * This method allows you to register a callable that will be executed when rendering a specific component or set of components.
     * The composer can modify the data passed to the view before rendering, allowing for dynamic data manipulation based on the component being rendered.
     *
     * @param string|array $components The name(s) of the component(s) to register the composer for. Use '*' to register for all components.
     * @param callable $composer The callable that will be executed when rendering the specified component(s). It receives the Inertia instance as an argument.
     * @return void
     */
    public static function composer(string|array $components, callable $composer): void
    {
        foreach ((array) $components as $component) {
            self::$composers[$component][] = $composer;
        }
    }

    /**
     * Create a lazy prop that is only evaluated during partial reloads.
     *
     * Lazy props are useful for expensive computations that should not be
     * included in the initial page load but can be loaded on demand.
     *
     * @param Closure $callback The callback that returns the prop value.
     * @return \Inertia\Props\LazyProp The lazy prop instance.
     */
    public static function lazy(Closure $callback): LazyProp
    {
        return new LazyProp($callback);
    }

    /**
     * Create an optional prop that is never included unless explicitly requested.
     *
     * This is an alias for lazy() to match the official Inertia API.
     * Optional props are not included in initial page load but can be
     * requested via partial reloads using the 'only' option.
     *
     * @param Closure $callback The callback that returns the prop value.
     * @return \Inertia\Props\LazyProp The lazy prop instance.
     */
    public static function optional(Closure $callback): LazyProp
    {
        return new LazyProp($callback);
    }

    /**
     * Create a deferred prop that is loaded after the initial page render.
     *
     * Deferred props are excluded from the initial page load and loaded in a
     * subsequent request, allowing the page to render faster.
     *
     * @param Closure $callback The callback that returns the prop value.
     * @param string $group The group name for batching deferred props (default: 'default').
     * @return \Inertia\Props\DeferredProp The deferred prop instance.
     */
    public static function defer(Closure $callback, string $group = 'default'): DeferredProp
    {
        return new DeferredProp($callback, $group);
    }

    /**
     * Create a merge prop that appends data to existing client-side data.
     *
     * Merge props are useful for infinite scrolling and real-time updates
     * where new data should be added to existing data rather than replacing it.
     *
     * @param Closure $callback The callback that returns the prop value.
     * @param string|null $matchBy Optional key path for matching items during merge.
     * @return \Inertia\Props\MergeProp The merge prop instance.
     */
    public static function merge(Closure $callback, ?string $matchBy = null): MergeProp
    {
        return new MergeProp($callback, MergeProp::MERGE_APPEND, $matchBy);
    }

    /**
     * Create a prepend merge prop that prepends data to existing client-side data.
     *
     * @param Closure $callback The callback that returns the prop value.
     * @param string|null $matchBy Optional key path for matching items during merge.
     * @return \Inertia\Props\MergeProp The merge prop instance.
     */
    public static function prepend(Closure $callback, ?string $matchBy = null): MergeProp
    {
        return new MergeProp($callback, MergeProp::MERGE_PREPEND, $matchBy);
    }

    /**
     * Create a deep merge prop that deep merges data with existing client-side data.
     *
     * @param Closure $callback The callback that returns the prop value.
     * @param string|null $matchBy Optional key path for matching items during merge.
     * @return \Inertia\Props\MergeProp The merge prop instance.
     */
    public static function deepMerge(Closure $callback, ?string $matchBy = null): MergeProp
    {
        return new MergeProp($callback, MergeProp::MERGE_DEEP, $matchBy);
    }

    /**
     * Create a once prop that is only evaluated and sent once.
     *
     * Once props are cached on the client and not re-fetched on subsequent
     * page visits, reducing server load for static data.
     *
     * @param Closure $callback The callback that returns the prop value.
     * @param string|null $key Optional custom key for tracking across pages.
     * @param int|null $expiresAt Optional expiration timestamp in milliseconds.
     * @return \Inertia\Props\OnceProp The once prop instance.
     */
    public static function once(Closure $callback, ?string $key = null, ?int $expiresAt = null): OnceProp
    {
        return new OnceProp($callback, $key, $expiresAt);
    }

    /**
     * Create an always prop that is always included even in partial reloads.
     *
     * Always props are useful for data that should always be fresh,
     * such as flash messages or notification counts.
     *
     * @param Closure $callback The callback that returns the prop value.
     * @return \Inertia\Props\AlwaysProp The always prop instance.
     */
    public static function always(Closure $callback): AlwaysProp
    {
        return new AlwaysProp($callback);
    }

    /**
     * Enable history encryption for the current response.
     *
     * @param bool $encrypt Whether to encrypt history (default: true).
     * @return static
     */
    public function withEncryptedHistory(bool $encrypt = true): static
    {
        $this->encryptHistory = $encrypt;
        return $this;
    }

    /**
     * Clear encrypted history on the client.
     *
     * @param bool $clear Whether to clear history (default: true).
     * @return static
     */
    public function withClearedHistory(bool $clear = true): static
    {
        $this->clearHistory = $clear;
        return $this;
    }

    /**
     * Render an Inertia component.
     *
     * This method checks if the request is an Inertia AJAX request. If it is, it returns a JSON response with the component data.
     * If it's not an AJAX request, it renders a view with the component data embedded in a 'page' variable.
     *
     * @param string $component The name of the Inertia component to render.
     * @param Arrayable|array $props An associative array of props to pass to the component.
     * @return Response The response instance containing the rendered component or JSON data.
     */
    public function render(string $component, Arrayable|array $props = []): Response
    {
        // Run any registered composers for the component
        $this->runComposers($component);

        // Convert Arrayable props to arrays
        if ($props instanceof Arrayable) {
            $props = $props->toArray();
        }

        // Include validation errors from the session in the shared data
        $errors = $this->request->errors()->all(merge: false);
        if (!empty($errors)) {
            $errors = collect($errors)
                ->mapWithKeys(fn($messages, $field) => [
                    $field => is_array($messages) ? $messages[0] : $messages
                ])
                ->all();
        }

        // Include flash messages from the session in the shared data
        $flash = array_filter([
            'info' => $this->request->session()->getFlash('info'),
            'success' => $this->request->session()->getFlash('success'),
            'error' => $this->request->session()->getFlash('error'),
        ]);

        // Include authenticated user information in the shared data
        $auth = [
            'user' => $this->request->user(...),
        ];

        // Merge shared props with component props (ensure errors prop exists)
        $props = ['errors' => (object) $errors, 'flash' => (object) $flash, 'auth' => $auth, ...self::$shared, ...$props];

        // Check if this is an Inertia request
        $isInertiaRequest = (bool) $this->request->header('X-Inertia');
        $isPartialReload = $isInertiaRequest && $this->isPartialReload($component);

        // Extract metadata about special prop types
        $metadata = $this->extractPropMetadata($props);

        // Prepare the base page data
        $page = [
            'component' => $component,
            'props' => $props,
            'url' => $this->request->getUri(),
            'version' => $this->version,
            'encryptHistory' => $this->encryptHistory,
            'clearHistory' => $this->clearHistory,
        ];

        // Add optional metadata arrays only if they have values
        if (!empty($metadata['deferredProps'])) {
            $page['deferredProps'] = $metadata['deferredProps'];
        }
        if (!empty($metadata['mergeProps'])) {
            $page['mergeProps'] = $metadata['mergeProps'];
        }
        if (!empty($metadata['prependProps'])) {
            $page['prependProps'] = $metadata['prependProps'];
        }
        if (!empty($metadata['deepMergeProps'])) {
            $page['deepMergeProps'] = $metadata['deepMergeProps'];
        }
        if (!empty($metadata['matchPropsOn'])) {
            $page['matchPropsOn'] = $metadata['matchPropsOn'];
        }
        if (!empty($metadata['onceProps'])) {
            $page['onceProps'] = $metadata['onceProps'];
        }

        // Process props for initial page load (non-Inertia request)
        if (!$isInertiaRequest) {
            $page['props'] = $this->processPropsForInitialLoad($props, $metadata);
            return view($this->rootView, compact('page'));
        }

        // Handle version mismatch - force full page reload (only for GET requests)
        // Per protocol: 409 responses are only sent for GET requests
        if ($this->hasVersionMismatch() && $this->request->isGet()) {
            return $this->forceRefresh();
        }

        // Process props based on request type
        $page['props'] = $this->processProps($props, $isPartialReload, $metadata);

        // If it's an Inertia AJAX request, return JSON
        return json($page)
            ->withHeaders(['X-Inertia' => 'true', 'Vary' => 'X-Inertia']);
    }

    /**
     * Extract metadata from props about special prop types.
     *
     * @param array $props The props array.
     * @return array Metadata arrays for deferred, merge, once props, etc.
     */
    protected function extractPropMetadata(array $props): array
    {
        $metadata = [
            'deferredProps' => [],
            'mergeProps' => [],
            'prependProps' => [],
            'deepMergeProps' => [],
            'matchPropsOn' => [],
            'onceProps' => [],
        ];

        foreach ($props as $key => $value) {
            if ($value instanceof DeferredProp) {
                $group = $value->getGroup();
                $metadata['deferredProps'][$group][] = $key;

                // Handle ->once() chaining on deferred props
                if ($value->isOnce()) {
                    $metadata['onceProps'][$key] = [
                        'prop' => $key,
                        'expiresAt' => null,
                    ];
                }

                // Handle merge config on deferred props
                if ($mergeConfig = $value->getMergeConfig()) {
                    if ($mergeConfig['strategy'] === MergeProp::MERGE_APPEND) {
                        $metadata['mergeProps'][] = $key;
                    } elseif ($mergeConfig['strategy'] === MergeProp::MERGE_DEEP) {
                        $metadata['deepMergeProps'][] = $key;
                    }
                }
            }

            if ($value instanceof MergeProp) {
                if ($value->isAppend()) {
                    $metadata['mergeProps'][] = $key;
                } elseif ($value->isPrepend()) {
                    $metadata['prependProps'][] = $key;
                } elseif ($value->isDeep()) {
                    $metadata['deepMergeProps'][] = $key;
                }

                if ($matchBy = $value->getMatchBy()) {
                    $metadata['matchPropsOn'][] = "{$key}.{$matchBy}";
                }

                // Handle ->once() chaining on merge props
                if ($value->isOnce()) {
                    $metadata['onceProps'][$key] = [
                        'prop' => $key,
                        'expiresAt' => null,
                    ];
                }
            }

            // Handle ->once() chaining on lazy/optional props
            if ($value instanceof LazyProp && $value->isOnce()) {
                $metadata['onceProps'][$key] = [
                    'prop' => $key,
                    'expiresAt' => null,
                ];
            }

            if ($value instanceof OnceProp) {
                $onceKey = $value->getKey() ?? $key;
                $metadata['onceProps'][$onceKey] = [
                    'prop' => $key,
                    'expiresAt' => $value->getExpiresAt(),
                ];
            }
        }

        return $metadata;
    }

    /**
     * Process props for initial page load (non-Inertia request).
     *
     * @param array $props The props to process.
     * @param array $metadata The prop metadata.
     * @return array The processed props.
     */
    protected function processPropsForInitialLoad(array $props, array $metadata): array
    {
        $result = [];

        foreach ($props as $key => $value) {
            // Skip deferred props on initial load - they'll be loaded in a subsequent request
            if ($value instanceof DeferredProp) {
                continue;
            }

            // Skip lazy props on initial load
            if ($value instanceof LazyProp) {
                continue;
            }

            // Resolve once props on initial load
            if ($value instanceof OnceProp) {
                $result[$key] = $this->resolveValue($value->resolve());
                continue;
            }

            // Resolve merge props
            if ($value instanceof MergeProp) {
                $result[$key] = $this->resolveValue($value->resolve());
                continue;
            }

            // Resolve always props
            if ($value instanceof AlwaysProp) {
                $result[$key] = $this->resolveValue($value->resolve());
                continue;
            }

            // Resolve closures
            if ($value instanceof Closure) {
                $result[$key] = $this->resolveValue($value());
                continue;
            }

            // Resolve other values
            $result[$key] = $this->resolveValue($value);
        }

        return $result;
    }

    /**
     * Force a full page refresh by returning 409 with X-Inertia-Location header.
     *
     * This is used when there's a version mismatch to ensure the client
     * reloads the page with fresh assets.
     *
     * @return Response The 409 response with location header.
     */
    public function forceRefresh(): Response
    {
        return response(statusCode: 409, headers: [
            'X-Inertia-Location' => $this->request->getUrl()
        ]);
    }

    /**
     * Perform an external redirect using a 409 Conflict response.
     *
     * @param string $url The URL to redirect to.
     * @return Response The 409 response with X-Inertia-Location header.
     */
    public function location(string $url): Response
    {
        return response(statusCode: 409, headers: [
            'X-Inertia-Location' => $url
        ]);
    }

    /**
     * Process props based on whether this is a partial reload or initial load.
     *
     * - On initial load: Excludes lazy/deferred props, resolves regular closures
     * - On partial reload: Only includes requested props, resolves all including lazy/deferred
     *
     * @param array $props The props to process.
     * @param bool $isPartialReload Whether this is a partial reload.
     * @param array $metadata The extracted prop metadata.
     * @return array The processed props.
     */
    protected function processProps(array $props, bool $isPartialReload, array $metadata = []): array
    {
        $result = [];
        $partialOnly = $isPartialReload ? $this->getPartialData() : [];
        $partialExcept = $isPartialReload ? $this->getPartialExcept() : [];
        $exceptOnceProps = $this->getExceptOnceProps();
        $resetProps = $this->getResetProps();

        foreach ($props as $key => $value) {
            // Check if this prop should be excluded via X-Inertia-Partial-Except
            if ($isPartialReload && !empty($partialExcept) && in_array($key, $partialExcept)) {
                continue;
            }

            // For partial reloads with X-Inertia-Partial-Data, only include requested props
            // But always include AlwaysProp instances and 'errors' prop
            $isAlwaysIncluded = $value instanceof AlwaysProp || in_array($key, ['errors', 'flash']);
            if ($isPartialReload && !empty($partialOnly) && !in_array($key, $partialOnly) && !$isAlwaysIncluded) {
                continue;
            }

            // Handle OnceProp - skip if client already has it cached
            // Unless explicitly requested via partial reload (force refresh) or fresh() is set
            if ($value instanceof OnceProp) {
                $onceKey = $value->getKey() ?? $key;
                $isExplicitlyRequested = $isPartialReload && !empty($partialOnly) && in_array($key, $partialOnly);
                $isFresh = $value->isFresh();
                if (in_array($onceKey, $exceptOnceProps) && !$isExplicitlyRequested && !$isFresh) {
                    // Client has this prop cached, don't include the value but keep metadata
                    continue;
                }
                $result[$key] = $this->resolveValue($value->resolve());
                continue;
            }

            // Handle DeferredProp - only resolve during partial reload when explicitly requested
            if ($value instanceof DeferredProp) {
                if ($isPartialReload && (empty($partialOnly) || in_array($key, $partialOnly))) {
                    $result[$key] = $this->resolveValue($value->resolve());
                }
                continue;
            }

            // Handle LazyProp instances
            if ($value instanceof LazyProp) {
                // Only include lazy props during partial reloads when explicitly requested
                if ($isPartialReload && (empty($partialOnly) || in_array($key, $partialOnly))) {
                    // Check for once behavior - skip if cached
                    if ($value->isOnce() && in_array($key, $exceptOnceProps)) {
                        continue;
                    }
                    $result[$key] = $this->resolveValue($value->resolve());
                }
                continue;
            }

            // Handle MergeProp instances
            if ($value instanceof MergeProp) {
                // Check for once behavior - skip if cached (unless explicitly requested)
                if ($value->isOnce()) {
                    $isExplicitlyRequested = $isPartialReload && !empty($partialOnly) && in_array($key, $partialOnly);
                    if (in_array($key, $exceptOnceProps) && !$isExplicitlyRequested) {
                        continue;
                    }
                }
                $result[$key] = $this->resolveValue($value->resolve());
                continue;
            }

            // Handle AlwaysProp instances (always resolve)
            if ($value instanceof AlwaysProp) {
                $result[$key] = $this->resolveValue($value->resolve());
                continue;
            }

            // Handle closures (always resolve them)
            if ($value instanceof Closure) {
                $result[$key] = $this->resolveValue($value());
                continue;
            }

            // Resolve other values
            $result[$key] = $this->resolveValue($value);
        }

        return $result;
    }

    /**
     * Resolve a value to its final form for JSON serialization.
     *
     * @param mixed $value The value to resolve.
     * @return mixed The resolved value.
     */
    protected function resolveValue(mixed $value): mixed
    {
        // If it's a Stringable or specific object, cast to string
        if (
            $value instanceof \Spark\Url ||
            $value instanceof \Spark\Utils\Carbon
        ) {
            return (string) $value;
        }

        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        }

        // Handle nested arrays recursively
        if (is_array($value)) {
            return $this->processNestedArray($value);
        }

        return $value;
    }

    /**
     * Process nested arrays recursively.
     *
     * @param array $array The array to process.
     * @return array The processed array.
     */
    protected function processNestedArray(array $array): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            if ($value instanceof LazyProp || $value instanceof DeferredProp) {
                // Skip lazy/deferred props in nested arrays
                continue;
            }

            if ($value instanceof Closure) {
                $result[$key] = $this->resolveValue($value());
                continue;
            }

            if ($value instanceof MergeProp || $value instanceof OnceProp || $value instanceof AlwaysProp) {
                $result[$key] = $this->resolveValue($value->resolve());
                continue;
            }

            $result[$key] = $this->resolveValue($value);
        }

        return $result;
    }

    /**
     * Render the root element for Inertia.js.
     *
     * This method returns an HTML string that contains a div with the id "app" and a data-page attribute.
     * The data-page attribute will be populated with the JSON-encoded page data when the view is rendered.
     *
     * @return \Spark\Support\HtmlString An instance of HtmlString containing the root element HTML.
     */
    public function renderRootElement(string|array $page = '{}'): HtmlString
    {
        if (is_array($page)) {
            $page = json_encode($page, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return new HtmlString(
            sprintf('<div id="app" data-page="%s"></div>', htmlspecialchars($page, ENT_QUOTES, 'UTF-8'))
        );
    }

    /**
     * Create an Inertia redirect response.
     *
     * This method handles redirects according to Inertia.js conventions:
     * - Uses 303 status code for PUT/PATCH/DELETE requests to prevent browser confirmation dialogs
     * - Uses 409 status with X-Inertia-Location header for external redirects
     * - Supports custom status codes for specific redirect scenarios
     *
     * @param string $url The URL to redirect to.
     * @param int $status The HTTP status code (default: 302). Use 303 for form submissions, 301 for permanent redirects.
     * @return Response The redirect response instance.
     */
    public function redirect(string $url, int $status = 302): Response
    {
        // For PUT, PATCH, DELETE requests, use 303 to prevent confirmation dialogs
        $method = strtoupper($this->request->getMethod());
        if (in_array($method, ['PUT', 'PATCH', 'DELETE']) && $status === 302) {
            $status = 303;
        }

        // Check if it's an external redirect (different domain)
        $isExternal = $this->isExternalUrl($url);

        // For external redirects with Inertia requests, use 409 with X-Inertia-Location header
        if ($isExternal && $this->request->header('X-Inertia')) {
            return response(statusCode: 409, headers: ['X-Inertia-Location' => $url]);
        }

        // Standard redirect - chain headers after redirect
        return redirect($url, $status);
    }

    /**
     * Create an Inertia redirect back to the previous page.
     *
     * @param int $status The HTTP status code (default: 302).
     * @return Response The redirect response instance.
     */
    public function back(int $status = 302): Response
    {
        $referer = $this->request->referer() ?: '/';

        return $this->redirect($referer, $status);
    }

    /**
     * Check if there is a version mismatch between client and server.
     *
     * @return bool True if versions don't match, false otherwise.
     */
    protected function hasVersionMismatch(): bool
    {
        $clientVersion = $this->request->header('X-Inertia-Version');

        // If no client version provided, no mismatch
        if ($clientVersion === null) {
            return false;
        }

        return $clientVersion !== $this->version;
    }

    /**
     * Check if this is a partial reload request for the given component.
     *
     * @param string $component The component being rendered.
     * @return bool True if this is a partial reload for this component.
     */
    protected function isPartialReload(string $component): bool
    {
        $partialComponent = $this->request->header('X-Inertia-Partial-Component');

        // Must have partial component header and it must match current component
        return $partialComponent !== null && $partialComponent === $component;
    }

    /**
     * Get the list of props requested in a partial reload.
     *
     * @return array List of prop names to include.
     */
    protected function getPartialData(): array
    {
        $partialData = $this->request->header('X-Inertia-Partial-Data');

        if ($partialData === null || $partialData === '') {
            return [];
        }

        return array_filter(explode(',', $partialData));
    }

    /**
     * Get the list of props to exclude from a partial reload.
     *
     * @return array List of prop names to exclude.
     */
    protected function getPartialExcept(): array
    {
        $partialExcept = $this->request->header('X-Inertia-Partial-Except');

        if ($partialExcept === null || $partialExcept === '') {
            return [];
        }

        return array_filter(explode(',', $partialExcept));
    }

    /**
     * Get the list of once props that the client already has cached.
     *
     * @return array List of once prop keys to skip.
     */
    protected function getExceptOnceProps(): array
    {
        $exceptOnce = $this->request->header('X-Inertia-Except-Once-Props');

        if ($exceptOnce === null || $exceptOnce === '') {
            return [];
        }

        return array_filter(explode(',', $exceptOnce));
    }

    /**
     * Check if this request is a prefetch request.
     *
     * @return bool True if this is a prefetch request.
     */
    protected function isPrefetchRequest(): bool
    {
        return $this->request->header('Purpose') === 'prefetch';
    }

    /**
     * Get the list of props that should be reset during partial reload.
     *
     * @return array List of prop names to reset.
     */
    protected function getResetProps(): array
    {
        $reset = $this->request->header('X-Inertia-Reset');

        if ($reset === null || $reset === '') {
            return [];
        }

        return array_filter(explode(',', $reset));
    }

    /**
     * Check if the given URL is external to the current application.
     *
     * @param string $url The URL to check.
     * @return bool True if the URL is external, false otherwise.
     */
    protected function isExternalUrl(string $url): bool
    {
        // If URL is relative or starts with /, it's internal
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            return false;
        }

        // Parse the URL and compare root URLs (protocol + host)
        $urlRoot = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST);
        if ($port = parse_url($url, PHP_URL_PORT)) {
            $urlRoot .= ":$port";
        }

        $requestRoot = $this->request->getRootUrl();

        return $urlRoot !== $requestRoot;
    }

    /**
     * Run registered composers for a given component.
     *
     * This method checks if there are any composers registered for the specified component and executes them.
     * It also checks for wildcard composers registered for all components and executes them as well.
     *
     * @param string $component The name of the component to run composers for.
     * @return void
     */
    protected function runComposers(string $component): void
    {
        if (isset(self::$composers[$component])) {
            foreach (self::$composers[$component] as $composer) {
                $composer($this);
            }
        }

        // Run wildcard composers
        if (isset(self::$composers['*'])) {
            foreach (self::$composers['*'] as $composer) {
                $composer($this);
            }
        }
    }
}
