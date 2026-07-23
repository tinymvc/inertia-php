<?php

namespace Inertia\Props\Concerns;

use function is_bool;
use function is_int;
use function is_string;

trait InteractsWithMerges
{
    protected bool $merge = false;

    protected bool $deepMerge = false;

    protected bool $appendAtRoot = true;

    /** @var array<int, string> */
    protected array $appendPaths = [];

    /** @var array<int, string> */
    protected array $prependPaths = [];

    /** @var array<int, string> */
    protected array $matchOn = [];

    public function merge(): static
    {
        $this->merge = true;

        return $this;
    }

    public function deepMerge(): static
    {
        $this->deepMerge = true;

        return $this->merge();
    }

    public function append(bool|string|array $path = true, ?string $matchOn = null): static
    {
        if (is_bool($path)) {
            $this->appendAtRoot = $path;
        } elseif (is_string($path)) {
            $this->appendPaths[] = $path;

            if ($matchOn !== null) {
                $this->matchOn([...$this->matchOn, "{$path}.{$matchOn}"]);
            }
        } else {
            foreach ($path as $key => $value) {
                is_int($key)
                    ? $this->append($value)
                    : $this->append((string) $key, $value);
            }
        }

        return $this;
    }

    public function prepend(bool|string|array $path = true, ?string $matchOn = null): static
    {
        if (is_bool($path)) {
            $this->appendAtRoot = !$path;
        } elseif (is_string($path)) {
            $this->prependPaths[] = $path;

            if ($matchOn !== null) {
                $this->matchOn([...$this->matchOn, "{$path}.{$matchOn}"]);
            }
        } else {
            foreach ($path as $key => $value) {
                is_int($key)
                    ? $this->prepend($value)
                    : $this->prepend((string) $key, $value);
            }
        }

        return $this;
    }

    public function matchOn(string|array $matchOn): static
    {
        $this->matchOn = array_values(array_unique((array) $matchOn));

        return $this;
    }

    public function shouldMerge(): bool
    {
        return $this->merge;
    }

    public function shouldDeepMerge(): bool
    {
        return $this->deepMerge;
    }

    public function appendsAtRoot(): bool
    {
        return $this->appendAtRoot && $this->mergesAtRoot();
    }

    public function prependsAtRoot(): bool
    {
        return !$this->appendAtRoot && $this->mergesAtRoot();
    }

    public function getAppendPaths(): array
    {
        return $this->appendPaths;
    }

    public function getPrependPaths(): array
    {
        return $this->prependPaths;
    }

    public function getMatchPaths(): array
    {
        return $this->matchOn;
    }

    protected function mergesAtRoot(): bool
    {
        return $this->appendPaths === [] && $this->prependPaths === [];
    }
}
