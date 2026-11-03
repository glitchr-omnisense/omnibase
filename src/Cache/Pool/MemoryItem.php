<?php

namespace Base\Cache\Pool;

use Psr\Cache\CacheItemInterface;

/**
 * An item of Base\Cache\Pool\MemoryPool (PSR-6). The parameters are left untyped so that it
 * implements psr/cache 1, 2 and 3 alike.
 */
final class MemoryItem implements CacheItemInterface
{
    private mixed $value = null;

    private bool $hit = false;

    private ?float $expiry = null;

    public function __construct(private readonly string $key)
    {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->hit ? $this->value : null;
    }

    public function isHit(): bool
    {
        return $this->hit && !$this->expired();
    }

    public function set($value): static
    {
        $this->value = $value;

        return $this;
    }

    public function expiresAt($expiration): static
    {
        $this->expiry = $expiration instanceof \DateTimeInterface ? (float) $expiration->format('U.u') : null;

        return $this;
    }

    public function expiresAfter($time): static
    {
        $this->expiry = match (true) {
            $time instanceof \DateInterval => (float) (new \DateTimeImmutable())->add($time)->format('U.u'),
            is_int($time) => microtime(true) + $time,
            default => null,
        };

        return $this;
    }

    /** @internal */
    public function expired(): bool
    {
        return null !== $this->expiry && microtime(true) >= $this->expiry;
    }

    /** @internal the item as the pool hands it back: saved, so a hit. */
    public function hit(): self
    {
        $this->hit = true;

        return $this;
    }
}
