<?php

namespace Inertia\Props;

use Inertia\Contracts\ProvidesScrollMetadata;
use InvalidArgumentException;
use Spark\Http\Request;
use function array_key_exists;
use function is_array;

class ScrollProp extends MergeProp
{
    protected mixed $resolvedValue;

    protected bool $hasResolved = false;

    protected bool $deferred = false;

    protected string $deferGroup = 'default';

    protected mixed $metadata;

    public function __construct(
        mixed $value,
        protected string $wrapper = 'data',
        ProvidesScrollMetadata|callable|null $metadata = null
    ) {
        parent::__construct($value);
        $this->metadata = $metadata;
    }

    public function defer(?string $group = null): static
    {
        $this->deferred = true;
        $this->deferGroup = $group ?? 'default';

        return $this;
    }

    public function shouldDefer(): bool
    {
        return $this->deferred;
    }

    public function getGroup(): string
    {
        return $this->deferGroup;
    }

    public function configureMergeIntent(Request $request): static
    {
        return $request->header('X-Inertia-Infinite-Scroll-Merge-Intent') === 'prepend'
            ? $this->prepend($this->wrapper)
            : $this->append($this->wrapper);
    }

    public function resolve(): mixed
    {
        if (!$this->hasResolved) {
            $this->resolvedValue = parent::resolve();
            $this->hasResolved = true;
        }

        return $this->resolvedValue;
    }

    public function getMetadata(): array
    {
        $provider = $this->metadata;

        if (is_callable($provider) && !$provider instanceof ProvidesScrollMetadata) {
            $provider = $provider($this->resolve());
        }

        $provider ??= ScrollMetadata::fromPaginator($this->resolve());

        if ($provider instanceof ProvidesScrollMetadata) {
            return [
                'pageName' => $provider->getPageName(),
                'previousPage' => $provider->getPreviousPage(),
                'nextPage' => $provider->getNextPage(),
                'currentPage' => $provider->getCurrentPage(),
            ];
        }

        if (is_array($provider)) {
            foreach (['pageName', 'previousPage', 'nextPage', 'currentPage'] as $key) {
                if (!array_key_exists($key, $provider)) {
                    throw new InvalidArgumentException("Scroll metadata is missing the [{$key}] key.");
                }
            }

            return $provider;
        }

        throw new InvalidArgumentException(
            'Scroll metadata callbacks must return an array or ProvidesScrollMetadata instance.'
        );
    }
}
