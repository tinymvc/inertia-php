<?php

namespace Inertia\Props\Concerns;

use BackedEnum;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use UnitEnum;

trait InteractsWithOnce
{
    protected bool $once = false;

    protected bool $fresh = false;

    protected ?string $onceKey = null;

    protected ?int $ttl = null;

    public function once(
        bool $value = true,
        BackedEnum|UnitEnum|string|null $as = null,
        DateTimeInterface|DateInterval|int|null $until = null
    ): static {
        $this->once = $value;

        if ($as !== null) {
            $this->as($as);
        }

        if ($until !== null) {
            $this->until($until);
        }

        return $this;
    }

    public function shouldResolveOnce(): bool
    {
        return $this->once;
    }

    public function fresh(bool $fresh = true): static
    {
        $this->fresh = $fresh;

        return $this;
    }

    public function shouldBeRefreshed(): bool
    {
        return $this->fresh;
    }

    public function as(BackedEnum|UnitEnum|string $key): static
    {
        $this->onceKey = match (true) {
            $key instanceof BackedEnum => (string) $key->value,
            $key instanceof UnitEnum => $key->name,
            default => $key,
        };

        return $this;
    }

    public function getKey(): ?string
    {
        return $this->onceKey;
    }

    public function until(DateTimeInterface|DateInterval|int $expiration): static
    {
        $this->ttl = match (true) {
            $expiration instanceof DateTimeInterface => max(0, $expiration->getTimestamp() - time()),
            $expiration instanceof DateInterval => max(
                0,
                (new DateTimeImmutable())->add($expiration)->getTimestamp() - time()
            ),
            default => $expiration,
        };

        return $this;
    }

    public function getExpiresAt(): ?int
    {
        return $this->ttl === null ? null : (time() + $this->ttl) * 1000;
    }
}
