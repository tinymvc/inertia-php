<?php

namespace Inertia;

use BackedEnum;
use Closure;
use Inertia\Contracts\InertiaAdapterContract;
use Inertia\Contracts\ProvidesScrollMetadata;
use Inertia\Props\AlwaysProp;
use Inertia\Props\DeferredProp;
use Inertia\Props\MergeProp;
use Inertia\Props\OnceProp;
use Inertia\Props\OptionalProp;
use Inertia\Props\ScrollProp;
use InvalidArgumentException;
use Spark\Contracts\Support\Arrayable;
use Spark\Foundation\Application;
use Spark\Http\Request;
use Spark\Http\Response;
use Spark\Support\HtmlString;
use UnitEnum;
use function array_key_exists;
use function in_array;
use function is_array;
use function sprintf;

/**
 * Inertia.js v3 server adapter for TinyMVC.
 *
 * This adapter intentionally provides client-side rendering only. Initial
 * visits receive an HTML root view and subsequent Inertia visits receive the
 * protocol page object as JSON.
 */
class Inertia implements InertiaAdapterContract
{
    protected const FLASH_SESSION_KEY = '__inertia_flash';

    protected const PRESERVE_FRAGMENT_SESSION_KEY = '__inertia_preserve_fragment';

    protected string $rootView = 'app';

    protected string $rootElementId = 'app';

    protected string $build = 'build';

    protected Closure|string|int|null $version = null;

    protected static array $shared = [];

    protected static array $composers = [];

    protected bool $encryptHistory = false;

    protected bool $clearHistory = false;

    protected bool $preserveFragment = false;

    public function __construct(protected Request $request)
    {
        $this->setBuildDirectory($this->build);
    }

    public static function instance(): Inertia
    {
        return Application::$app->get(Inertia::class);
    }

    public function setRootView(string $view): void
    {
        $this->rootView = $view;
    }

    public function setRootElementId(string $id): void
    {
        $this->rootElementId = $id;
    }

    public function setBuildDirectory(string $build): void
    {
        $this->build = trim($build, '/');
        $manifest = root_dir(
            sprintf('public/%s/.vite/manifest.json', $this->build)
        );

        if (is_file($manifest)) {
            $hash = hash_file('xxh128', $manifest);
            $this->version = $hash === false ? null : $hash;
        }
    }

    public function setVersion(Closure|string|int|null $version): void
    {
        $this->version = $version;
    }

    /**
     * Official v3-compatible alias for setVersion().
     */
    public function version(Closure|string|int|null $version): void
    {
        $this->setVersion($version);
    }

    public function getVersion(): string
    {
        $version = $this->version instanceof Closure
            ? call($this->version)
            : $this->version;

        return (string) $version;
    }

    public static function share(
        array|string|Arrayable|ProvidesInertiaProperties $key,
        mixed $value = null
    ): void {
        if ($key instanceof Arrayable) {
            $key = $key->toArray();
        }

        if ($key instanceof ProvidesInertiaProperties) {
            self::$shared[] = $key;
            return;
        }

        if (is_array($key)) {
            self::$shared = [...self::$shared, ...$key];
            return;
        }

        self::$shared[$key] = $value;
    }

    public static function getShared(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return self::$shared;
        }

        $value = self::$shared;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public static function shareOnce(string $key, callable $callback): OnceProp
    {
        $prop = new OnceProp($callback);
        self::$shared[$key] = $prop;

        return $prop;
    }

    public static function flushShared(): void
    {
        self::$shared = [];
        self::$composers = [];
    }

    public static function composer(string|array $components, callable $composer): void
    {
        foreach ((array) $components as $component) {
            self::$composers[$component][] = $composer;
        }
    }

    public static function optional(callable $callback): OptionalProp
    {
        return new OptionalProp($callback);
    }

    public static function defer(
        callable $callback,
        string $group = 'default',
        bool $rescue = false
    ): DeferredProp {
        return new DeferredProp($callback, $group, $rescue);
    }

    public static function merge(mixed $value): MergeProp
    {
        return new MergeProp($value);
    }

    public static function prepend(mixed $value, ?string $matchOn = null): MergeProp
    {
        $prop = (new MergeProp($value))->prepend();

        return $matchOn === null ? $prop : $prop->matchOn($matchOn);
    }

    public static function deepMerge(mixed $value, string|array|null $matchOn = null): MergeProp
    {
        $prop = (new MergeProp($value))->deepMerge();

        return $matchOn === null ? $prop : $prop->matchOn($matchOn);
    }

    public static function once(callable $callback): OnceProp
    {
        return new OnceProp($callback);
    }

    public static function always(mixed $value): AlwaysProp
    {
        return new AlwaysProp($value);
    }

    public static function scroll(
        mixed $value,
        string $wrapper = 'data',
        ProvidesScrollMetadata|callable|null $metadata = null
    ): ScrollProp {
        return new ScrollProp($value, $wrapper, $metadata);
    }

    public function withEncryptedHistory(bool $encrypt = true): static
    {
        $this->encryptHistory = $encrypt;

        return $this;
    }

    public function encryptHistory(bool $encrypt = true): static
    {
        return $this->withEncryptedHistory($encrypt);
    }

    public function withClearedHistory(bool $clear = true): static
    {
        $this->clearHistory = $clear;

        return $this;
    }

    public function clearHistory(bool $clear = true): static
    {
        return $this->withClearedHistory($clear);
    }

    /**
     * Preserve the current URL fragment across the next redirect.
     */
    public function preserveFragment(bool $preserve = true): static
    {
        $this->preserveFragment = $preserve;

        if ($preserve) {
            $this->request->session()->flash(self::PRESERVE_FRAGMENT_SESSION_KEY, true);
        }

        return $this;
    }

    /**
     * Store one-time page data under page.flash for the next Inertia response.
     */
    public function flash(BackedEnum|UnitEnum|string|array $key, mixed $value = null): static
    {
        if (!is_array($key)) {
            $key = match (true) {
                $key instanceof BackedEnum => (string) $key->value,
                $key instanceof UnitEnum => $key->name,
                default => $key,
            };
            $key = [$key => $value];
        }

        $current = $this->request->session()->getFlash(self::FLASH_SESSION_KEY, []);
        $this->request->session()->flash(
            self::FLASH_SESSION_KEY,
            [...is_array($current) ? $current : [], ...$key]
        );

        return $this;
    }

    public function render(
        BackedEnum|UnitEnum|string $component,
        Arrayable|ProvidesInertiaProperties|array $props = []
    ): Response {
        if ($component instanceof BackedEnum && !is_string($component->value)) {
            throw new InvalidArgumentException(
                'Component argument must be a string or a string-backed enum.'
            );
        }

        $component = match (true) {
            $component instanceof BackedEnum => (string) $component->value,
            $component instanceof UnitEnum => $component->name,
            default => $component,
        };

        $this->runComposers($component);

        if ($props instanceof Arrayable) {
            $props = $props->toArray();
        } elseif ($props instanceof ProvidesInertiaProperties) {
            $props = [$props];
        }

        $isInertiaRequest = (bool) $this->request->header('X-Inertia');

        if ($isInertiaRequest && $this->request->isGet() && $this->hasVersionMismatch()) {
            return $this->forceRefresh();
        }

        $shared = [
            'errors' => new AlwaysProp($this->resolveValidationErrors()),
            ...self::$shared,
        ];

        [$resolvedProps, $metadata] = (new PropsResolver($this->request, $component))
            ->resolve($shared, $props);

        $page = [
            'component' => $component,
            'props' => $resolvedProps,
            'url' => $this->request->getUri(),
            'version' => $this->getVersion(),
            ...$metadata,
        ];

        if ($this->encryptHistory) {
            $page['encryptHistory'] = true;
        }

        if ($this->clearHistory) {
            $page['clearHistory'] = true;
        }

        if (
            $this->preserveFragment
            || $this->request->session()->getFlash(self::PRESERVE_FRAGMENT_SESSION_KEY, false)
        ) {
            $page['preserveFragment'] = true;
        }

        if ($flash = $this->pullFlashData()) {
            $page['flash'] = $flash;
        }

        if (!$isInertiaRequest) {
            return view($this->rootView, compact('page'))
                ->withHeaders(['Vary' => 'X-Inertia']);
        }

        return json(
            $page,
            flags: JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        )->withHeaders([
                    'X-Inertia' => 'true',
                    'Vary' => 'X-Inertia',
                ]);
    }

    public function forceRefresh(): Response
    {
        return response('', 409, [
            'X-Inertia-Location' => $this->request->getUrl(),
            'X-Inertia-Version' => $this->getVersion(),
            'Vary' => 'X-Inertia',
        ]);
    }

    public function location(string $url): Response
    {
        if (!$this->request->header('X-Inertia')) {
            return redirect($url);
        }

        return response('', 409, [
            'X-Inertia-Location' => $url,
            'Vary' => 'X-Inertia',
        ]);
    }

    public function redirect(string $url, int $status = 302): Response
    {
        if (
            $status === 302
            && in_array($this->request->getMethod(), ['PUT', 'PATCH', 'DELETE'], true)
        ) {
            $status = 303;
        }

        if ($this->request->header('X-Inertia')) {
            if ($this->isExternalUrl($url)) {
                return $this->location($url);
            }

            if (
                parse_url($url, PHP_URL_FRAGMENT) !== null
                && $this->request->header('Purpose') !== 'prefetch'
            ) {
                return response('', 409, [
                    'X-Inertia-Redirect' => $url,
                    'Vary' => 'X-Inertia',
                ]);
            }
        }

        return redirect($url, $status)->withHeaders(['Vary' => 'X-Inertia']);
    }

    public function back(int $status = 302): Response
    {
        return $this->redirect($this->request->referer() ?: '/', $status);
    }

    /**
     * Render the v3 script-based initial page payload and client mount point.
     */
    public function renderRootElement(string|array $page = '{}', ?string $id = null): HtmlString
    {
        $id ??= $this->rootElementId;
        $escapedId = htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $page = is_array($page) ? json_encode(
            $page,
            JSON_THROW_ON_ERROR
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        ) : str_replace(
            ['<', '>', '&'],
            ['\\u003C', '\\u003E', '\\u0026'],
            $page
        );

        return new HtmlString(sprintf(
            '<script data-page="%s" type="application/json">%s</script><div id="%s"></div>',
            $escapedId,
            $page,
            $escapedId
        ));
    }

    protected function resolveValidationErrors(): object
    {
        $resolved = [];

        foreach ($this->request->errors()->all(merge: false) as $field => $messages) {
            $resolved[$field] = is_array($messages)
                ? ($messages[0] ?? null)
                : $messages;
        }

        if ($bag = $this->request->header('X-Inertia-Error-Bag')) {
            return (object) [$bag => (object) $resolved];
        }

        return (object) $resolved;
    }

    protected function pullFlashData(): array
    {
        $session = $this->request->session();
        $flash = $session->getFlash(self::FLASH_SESSION_KEY, []);
        $flash = is_array($flash) ? $flash : [];

        foreach (['info', 'success', 'error'] as $key) {
            $value = $session->getFlash($key);

            if ($value !== null) {
                $flash[$key] = $value;
            }
        }

        return $flash;
    }

    protected function hasVersionMismatch(): bool
    {
        return (string) $this->request->header('X-Inertia-Version', '') !== $this->getVersion();
    }

    protected function isExternalUrl(string $url): bool
    {
        if (!preg_match('/^https?:\/\//i', $url)) {
            return false;
        }

        $urlRoot = sprintf(
            '%s://%s%s',
            parse_url($url, PHP_URL_SCHEME),
            parse_url($url, PHP_URL_HOST),
            ($port = parse_url($url, PHP_URL_PORT)) ? ":{$port}" : ''
        );

        return $urlRoot !== $this->request->getRootUrl();
    }

    protected function runComposers(string $component): void
    {
        foreach (self::$composers[$component] ?? [] as $composer) {
            $composer($this);
        }

        foreach (self::$composers['*'] ?? [] as $composer) {
            $composer($this);
        }
    }
}
