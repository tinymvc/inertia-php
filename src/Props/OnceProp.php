<?php

namespace Inertia\Props;

use Closure;
use DateTimeInterface;
use DateInterval;

/**
 * OnceProp
 *
 * Once props are only evaluated and sent to the client once. On subsequent page visits where the client already 
 * has the prop value cached, the prop is skipped, reducing server load and 
 * response size.
 *
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 * @link https://inertiajs.com/docs/v2/data-props/once-props
 */
class OnceProp extends BaseProp
{
    /**
     * Whether to force refresh this prop (ignore client cache).
     *
     * @var bool
     */
    protected bool $fresh = false;

    /**
     * Create a new once prop instance.
     *
     * @param Closure $callback The callback that returns the prop value when evaluated.
     * @param string|null $key Optional custom key for tracking across pages (defaults to prop name).
     * @param int|null $expiresAt Optional expiration timestamp in milliseconds.
     */
    public function __construct(
        protected Closure $callback,
        protected ?string $key = null,
        protected ?int $expiresAt = null
    ) {
    }

    /**
     * Set a custom key for tracking this prop across pages.
     *
     * @param string $key The custom key.
     * @return static
     */
    public function as(string $key): static
    {
        $this->key = $key;
        return $this;
    }

    /**
     * Force this prop to be refreshed (ignore client cache).
     *
     * @param bool $fresh Whether to force refresh.
     * @return static
     */
    public function fresh(bool $fresh = true): static
    {
        $this->fresh = $fresh;
        return $this;
    }

    /**
     * Set an expiration time for this prop.
     *
     * @param DateTimeInterface|DateInterval|int $expiration The expiration time.
     * @return static
     */
    public function until(DateTimeInterface|DateInterval|int $expiration): static
    {
        if ($expiration instanceof DateTimeInterface) {
            // Convert to milliseconds timestamp
            $this->expiresAt = (int) ($expiration->getTimestamp() * 1000);
        } elseif ($expiration instanceof DateInterval) {
            // Convert interval to milliseconds from now
            $now = new \DateTime();
            $future = (clone $now)->add($expiration);
            $this->expiresAt = (int) ($future->getTimestamp() * 1000);
        } else {
            // Assume seconds, convert to milliseconds timestamp
            $this->expiresAt = (int) ((time() + $expiration) * 1000);
        }
        return $this;
    }

    /**
     * Get the custom key for this once prop.
     *
     * @return string|null The custom key.
     */
    public function getKey(): ?string
    {
        return $this->key;
    }

    /**
     * Get the expiration timestamp.
     *
     * @return int|null The expiration timestamp in milliseconds.
     */
    public function getExpiresAt(): ?int
    {
        return $this->expiresAt;
    }

    /**
     * Check if this prop should be force refreshed.
     *
     * @return bool True if fresh.
     */
    public function isFresh(): bool
    {
        return $this->fresh;
    }
}
