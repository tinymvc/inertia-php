<?php

namespace Inertia;

use Closure;
use JsonSerializable;
use Inertia\Props\{
    AlwaysProp,
    BaseProp,
    DeferredProp,
    MergeProp,
    OnceProp,
    OptionalProp,
    ScrollProp
};
use Spark\Contracts\Support\Arrayable;
use Spark\Http\{Header, Request};
use Spark\Http\Resources\JsonResource;
use Spark\Http\Resources\MissingValue;
use Spark\Support\Enumerable;
use Throwable;
use function in_array;
use function is_array;
use function is_int;
use function is_object;
use function is_string;
use function sprintf;

/**
 * Resolves a page's complete prop tree and builds Inertia v3 page metadata.
 */
class PropsResolver
{
    protected bool $isInertia;

    protected bool $isPartial;

    /** @var array<int, string>|null */
    protected ?array $only;

    /** @var array<int, string>|null */
    protected ?array $except;

    /** @var array<int, string> */
    protected array $resetProps;

    /** @var array<int, string> */
    protected array $loadedOnceProps;

    /** @var array<string, array<int, string>> */
    protected array $deferredProps = [];

    /** @var array<int, string> */
    protected array $rescuedProps = [];

    /** @var array<int, string> */
    protected array $mergeProps = [];

    /** @var array<int, string> */
    protected array $prependProps = [];

    /** @var array<int, string> */
    protected array $deepMergeProps = [];

    /** @var array<int, string> */
    protected array $matchPropsOn = [];

    /** @var array<string, array<string, mixed>> */
    protected array $scrollProps = [];

    /** @var array<string, array{prop: string, expiresAt: int|null}> */
    protected array $onceProps = [];

    /** @var array<int, string> */
    protected array $sharedPropKeys = [];

    public function __construct(
        protected Request $request,
        protected string $component
    ) {
        $this->isInertia = (bool) $request->header(Header::X_INERTIA);
        $this->isPartial = $this->isInertia
            && $request->header(Header::X_INERTIA_PARTIAL_COMPONENT) === $component;
        $this->only = $this->parseHeader(Header::X_INERTIA_PARTIAL_DATA);
        $this->except = $this->parseHeader(Header::X_INERTIA_PARTIAL_EXCEPT);
        $this->resetProps = $this->parseHeader(Header::X_INERTIA_RESET) ?? [];
        $this->loadedOnceProps = $this->parseHeader(Header::X_INERTIA_EXCEPT_ONCE_PROPS) ?? [];
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public function resolve(array $shared, array $props): array
    {
        $this->deferredProps = [];
        $this->rescuedProps = [];
        $this->mergeProps = [];
        $this->prependProps = [];
        $this->deepMergeProps = [];
        $this->matchPropsOn = [];
        $this->scrollProps = [];
        $this->onceProps = [];

        $shared = $this->resolvePropertyProviders($shared);
        $this->sharedPropKeys = array_values(array_unique(array_map(
            static fn(string|int $key): string => explode('.', (string) $key, 2)[0],
            array_keys($shared)
        )));

        $props = [...$shared, ...$this->resolvePropertyProviders($props)];

        return [
            $this->resolveProps($this->unpackDotProps($props)),
            $this->buildMetadata(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveProps(array $props, string $prefix = '', bool $parentWasResolved = false): array
    {
        $props = $this->resolvePropertyProviders($props);
        $isList = array_is_list($props);
        $result = [];

        foreach ($props as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            $prop = $value;

            if (!$this->shouldIncludeInPartialResponse($prop, $path, $parentWasResolved)) {
                continue;
            }

            if (!$this->isPartial && $this->excludeFromInitialResponse($prop, $path)) {
                continue;
            }

            $value = $this->resolveValue($prop, $path, $props);

            if ($value === MissingValue::Missing || in_array($path, $this->rescuedProps, true)) {
                continue;
            }

            if ($value !== $prop && $this->isPropType($value)) {
                $prop = $value;

                if (!$this->isPartial && $this->excludeFromInitialResponse($prop, $path)) {
                    continue;
                }

                $value = $this->resolveValue($prop, $path, $props);
            }

            if ($value === MissingValue::Missing || in_array($path, $this->rescuedProps, true)) {
                continue;
            }

            $this->collectMetadata($prop, $path);

            $result[$key] = is_array($value)
                ? $this->resolveProps($value, $path, $parentWasResolved || !is_array($prop))
                : $value;
        }

        return $isList ? array_values($result) : $result;
    }

    protected function shouldIncludeInPartialResponse(
        mixed $prop,
        string $path,
        bool $parentWasResolved
    ): bool {
        if (!$this->isPartial || $prop instanceof AlwaysProp || $parentWasResolved) {
            return true;
        }

        if ($this->only !== null && !$this->matchesOnly($path) && !$this->leadsToOnly($path)) {
            return false;
        }

        if ($this->except !== null && $this->matchesExcept($path)) {
            return false;
        }

        return true;
    }

    protected function excludeFromInitialResponse(mixed $prop, string $path): bool
    {
        if ($prop instanceof OptionalProp) {
            $this->collectOnceMetadata($prop, $path);

            return true;
        }

        if ($prop instanceof DeferredProp) {
            if (!$this->wasAlreadyLoadedByClient($prop, $path)) {
                $this->deferredProps[$prop->getGroup()][] = $path;
            }

            $this->collectMergeMetadata($prop, $path);
            $this->collectOnceMetadata($prop, $path);

            return true;
        }

        if ($prop instanceof ScrollProp && $prop->shouldDefer()) {
            $this->deferredProps[$prop->getGroup()][] = $path;
            $prop->configureMergeIntent($this->request);
            $this->collectMergeMetadata($prop, $path);

            return true;
        }

        if ($this->isInertia && $this->wasAlreadyLoadedByClient($prop, $path)) {
            $this->collectOnceMetadata($prop, $path);

            return true;
        }

        return false;
    }

    protected function resolveValue(mixed $value, string $path, array $siblings): mixed
    {
        if ($value instanceof ScrollProp) {
            $value->configureMergeIntent($this->request);
        }

        $shouldRescue = $value instanceof DeferredProp && $value->shouldRescue();

        try {
            if ($value instanceof BaseProp) {
                $value = $value->resolve();
            } elseif ($value instanceof Closure) {
                $value = $value();
            }

            if ($value instanceof ProvidesInertiaProperty) {
                $value = $value->toInertiaProperty(
                    new PropertyContext($path, $siblings, $this->request)
                );
            }

            // Preserve wrappers and children until their own selection and metadata
            // have been processed. Recursive normalization evaluates lazy props.
            if ($this->isPropType($value) || is_array($value)) {
                return $value;
            }

            if ($value instanceof Enumerable) {
                return $value->all();
            }

            if ($value instanceof Arrayable && !$value instanceof JsonResource) {
                return $value->toArray();
            }

            if ($value instanceof JsonSerializable && !$value instanceof JsonResource) {
                return $value->jsonSerialize();
            }

            return JsonResource::normalize($value, $this->request);
        } catch (Throwable $exception) {
            if (!$shouldRescue) {
                throw $exception;
            }

            error_log(sprintf(
                'Inertia rescued deferred prop [%s]: %s',
                $path,
                $exception->getMessage()
            ));

            $this->rescuedProps[] = $path;

            return null;
        }
    }

    protected function collectMetadata(mixed $prop, string $path): void
    {
        if ($prop instanceof DeferredProp) {
            $this->collectMergeMetadata($prop, $path);
            $this->collectOnceMetadata($prop, $path);
        }

        if ($prop instanceof MergeProp) {
            $this->collectMergeMetadata($prop, $path);
            $this->collectOnceMetadata($prop, $path);
        }

        if ($prop instanceof OptionalProp || $prop instanceof OnceProp) {
            $this->collectOnceMetadata($prop, $path);
        }

        if ($prop instanceof ScrollProp) {
            $this->scrollProps[$path] = [
                ...$prop->getMetadata(),
                'reset' => in_array($path, $this->resetProps, true),
            ];
        }
    }

    protected function collectMergeMetadata(MergeProp|DeferredProp $prop, string $path): void
    {
        if (!$prop->shouldMerge() || in_array($path, $this->resetProps, true)) {
            return;
        }

        if ($this->isPartial && !$this->isIncludedInPartialMetadata($path)) {
            return;
        }

        if ($prop->shouldDeepMerge()) {
            $this->deepMergeProps[] = $path;
        } elseif ($prop->appendsAtRoot()) {
            $this->mergeProps[] = $path;
        } elseif ($prop->prependsAtRoot()) {
            $this->prependProps[] = $path;
        } else {
            foreach ($prop->getAppendPaths() as $appendPath) {
                $this->mergeProps[] = "{$path}.{$appendPath}";
            }

            foreach ($prop->getPrependPaths() as $prependPath) {
                $this->prependProps[] = "{$path}.{$prependPath}";
            }
        }

        foreach ($prop->getMatchPaths() as $matchPath) {
            $this->matchPropsOn[] = "{$path}.{$matchPath}";
        }
    }

    protected function collectOnceMetadata(mixed $prop, string $path): void
    {
        if (
            !is_object($prop)
            || !method_exists($prop, 'shouldResolveOnce')
            || !$prop->shouldResolveOnce()
        ) {
            return;
        }

        if ($this->isPartial && !$this->isIncludedInPartialMetadata($path)) {
            return;
        }

        $this->onceProps[$prop->getKey() ?? $path] = [
            'prop' => $path,
            'expiresAt' => $prop->getExpiresAt(),
        ];
    }

    protected function wasAlreadyLoadedByClient(mixed $prop, string $path): bool
    {
        return is_object($prop)
            && method_exists($prop, 'shouldResolveOnce')
            && $prop->shouldResolveOnce()
            && !$prop->shouldBeRefreshed()
            && in_array($prop->getKey() ?? $path, $this->loadedOnceProps, true);
    }

    protected function isIncludedInPartialMetadata(string $path): bool
    {
        if ($this->only !== null && !$this->matchesOnly($path)) {
            return false;
        }

        return $this->except === null || !$this->matchesExcept($path);
    }

    protected function matchesOnly(string $path): bool
    {
        foreach ($this->only ?? [] as $onlyPath) {
            if ($path === $onlyPath || str_starts_with($path, "{$onlyPath}.")) {
                return true;
            }
        }

        return false;
    }

    protected function leadsToOnly(string $path): bool
    {
        foreach ($this->only ?? [] as $onlyPath) {
            if (str_starts_with($onlyPath, "{$path}.")) {
                return true;
            }
        }

        return false;
    }

    protected function matchesExcept(string $path): bool
    {
        foreach ($this->except ?? [] as $exceptPath) {
            if ($path === $exceptPath || str_starts_with($path, "{$exceptPath}.")) {
                return true;
            }
        }

        return false;
    }

    protected function isPropType(mixed $value): bool
    {
        return $value instanceof AlwaysProp
            || $value instanceof DeferredProp
            || $value instanceof MergeProp
            || $value instanceof OnceProp
            || $value instanceof OptionalProp;
    }

    protected function unpackDotProps(array $props): array
    {
        foreach (array_keys($props) as $key) {
            if (!is_string($key) || !str_contains($key, '.')) {
                continue;
            }

            $value = $props[$key];
            unset($props[$key]);

            $segments = explode('.', $key);
            $current = &$props;

            foreach ($segments as $index => $segment) {
                if ($index === array_key_last($segments)) {
                    $current[$segment] = $value;
                    break;
                }

                if (isset($current[$segment]) && $current[$segment] instanceof Closure) {
                    $current[$segment] = $current[$segment]();
                }

                if (isset($current[$segment]) && $current[$segment] instanceof Arrayable) {
                    $current[$segment] = $current[$segment]->toArray();
                }

                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }

                $current = &$current[$segment];
            }

            unset($current);
        }

        return $props;
    }

    protected function resolvePropertyProviders(array $props): array
    {
        $result = [];
        $context = null;

        foreach ($props as $key => $value) {
            if (is_int($key) && $value instanceof ProvidesInertiaProperties) {
                $context ??= new RenderContext($this->component, $this->request);

                foreach ($value->toInertiaProperties($context) as $providedKey => $providedValue) {
                    $result[$providedKey] = $providedValue;
                }

                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    protected function buildMetadata(): array
    {
        $metadata = [
            'sharedProps' => $this->sharedPropKeys,
            'mergeProps' => array_values(array_unique($this->mergeProps)),
            'prependProps' => array_values(array_unique($this->prependProps)),
            'deepMergeProps' => array_values(array_unique($this->deepMergeProps)),
            'matchPropsOn' => array_values(array_unique($this->matchPropsOn)),
            'deferredProps' => $this->uniqueGroupedProps($this->deferredProps),
            'rescuedProps' => array_values(array_unique($this->rescuedProps)),
            'scrollProps' => $this->scrollProps,
            'onceProps' => $this->onceProps,
        ];

        return array_filter($metadata, static fn(array $value): bool => $value !== []);
    }

    protected function uniqueGroupedProps(array $groups): array
    {
        foreach ($groups as $group => $props) {
            $groups[$group] = array_values(array_unique($props));
        }

        return $groups;
    }

    /**
     * @return array<int, string>|null
     */
    protected function parseHeader(string $header): ?array
    {
        $value = $this->request->header($header, '');
        $items = array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) $value)
        ), static fn(string $item): bool => $item !== ''));

        return $items === [] ? null : $items;
    }
}
