<?php

namespace Inertia\Props;

use Closure;

/**
 * MergeProp
 *
 * Merge props allow you to merge new data with existing data on the client-side during navigation. This is useful 
 * for infinite scrolling, real-time updates, and other scenarios where you want 
 * to add to existing data rather than replace it.
 *
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 * @link https://inertiajs.com/docs/v2/data-props/merging-props
 */
class MergeProp extends BaseProp
{
    /**
     * The merge strategy constants.
     */
    public const MERGE_APPEND = 'merge';
    public const MERGE_PREPEND = 'prepend';
    public const MERGE_DEEP = 'deep';

    /**
     * Whether this merge prop should be cached using once behavior.
     *
     * @var bool
     */
    protected bool $once = false;

    /**
     * Nested paths to append to.
     *
     * @var array
     */
    protected array $appendPaths = [];

    /**
     * Nested paths to prepend to.
     *
     * @var array
     */
    protected array $prependPaths = [];

    /**
     * Create a new merge prop instance.
     *
     * @param Closure $callback The callback that returns the prop value when evaluated.
     * @param string $strategy The merge strategy: 'merge' (append), 'prepend', or 'deep'.
     * @param string|null $matchBy Optional key path for matching items during merge (e.g., 'id').
     */
    public function __construct(
        protected Closure $callback,
        protected string $strategy = self::MERGE_APPEND,
        protected ?string $matchBy = null
    ) {
    }

    /**
     * Get the merge strategy.
     *
     * @return string The merge strategy.
     */
    public function getStrategy(): string
    {
        return $this->strategy;
    }

    /**
     * Get the match key path for deduplication.
     *
     * @return string|null The match key path.
     */
    public function getMatchBy(): ?string
    {
        return $this->matchBy;
    }

    /**
     * Check if this is an append merge.
     *
     * @return bool True if append merge.
     */
    public function isAppend(): bool
    {
        return $this->strategy === self::MERGE_APPEND;
    }

    /**
     * Check if this is a prepend merge.
     *
     * @return bool True if prepend merge.
     */
    public function isPrepend(): bool
    {
        return $this->strategy === self::MERGE_PREPEND;
    }

    /**
     * Check if this is a deep merge.
     *
     * @return bool True if deep merge.
     */
    public function isDeep(): bool
    {
        return $this->strategy === self::MERGE_DEEP;
    }

    /**
     * Mark this merge prop to be cached (once behavior).
     *
     * @return static
     */
    public function once(): static
    {
        $this->once = true;
        return $this;
    }

    /**
     * Check if this prop has once behavior.
     *
     * @return bool True if should be cached.
     */
    public function isOnce(): bool
    {
        return $this->once;
    }

    /**
     * Set the append path(s) for nested array merging.
     *
     * @param string|array $paths The nested path(s) to append to.
     * @param string|null $matchOn Optional match key for this path.
     * @return static
     */
    public function append(string|array $paths, ?string $matchOn = null): static
    {
        $this->strategy = self::MERGE_APPEND;
        if (is_string($paths)) {
            $this->appendPaths[$paths] = $matchOn;
        } else {
            foreach ($paths as $path => $match) {
                if (is_int($path)) {
                    $this->appendPaths[$match] = null;
                } else {
                    $this->appendPaths[$path] = $match;
                }
            }
        }
        return $this;
    }

    /**
     * Set the prepend path(s) for nested array merging.
     *
     * @param string|array|null $paths The nested path(s) to prepend to.
     * @return static
     */
    public function prepend(string|array|null $paths = null): static
    {
        $this->strategy = self::MERGE_PREPEND;
        if ($paths !== null) {
            if (is_string($paths)) {
                $this->prependPaths[] = $paths;
            } else {
                $this->prependPaths = array_merge($this->prependPaths, $paths);
            }
        }
        return $this;
    }

    /**
     * Set the match key for deduplication during merge.
     *
     * @param string $key The key path for matching.
     * @return static
     */
    public function matchOn(string $key): static
    {
        $this->matchBy = $key;
        return $this;
    }

    /**
     * Get the append paths.
     *
     * @return array The append paths.
     */
    public function getAppendPaths(): array
    {
        return $this->appendPaths;
    }

    /**
     * Get the prepend paths.
     *
     * @return array The prepend paths.
     */
    public function getPrependPaths(): array
    {
        return $this->prependPaths;
    }
}
