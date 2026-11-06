<?php

namespace Base\Console\Task;

/**
 * How many times the cache was cleared since it was made, kept in a file beside it (<cache dir>.txt): the first
 * clear of an application that has not started yet, the second one that cache:clear runs by itself
 * (base.autoclear). omnibridge/symfony's CacheClearCommand answers its static questions from it (plan
 * "omnibase agnostic", step F5e, slice 3).
 */
final class CacheClears
{
    public function __construct(public readonly string $file)
    {
    }

    public function count(): int
    {
        return file_exists($this->file) ? (int) file_get_contents($this->file) : 0;
    }

    /** No clear yet: the application has not started. */
    public function notStarted(): bool
    {
        return $this->count() < 1;
    }

    public function isFirst(): bool
    {
        return $this->count() < 2;
    }

    /** The counter set to none, when $first. */
    public function markFirst(bool $first = true): void
    {
        if ($first) {
            file_put_contents($this->file, 0);
        }
    }

    /** One more clear (the counter made first if it was not there). */
    public function increment(): void
    {
        $this->markFirst(!file_exists($this->file));
        file_put_contents($this->file, $this->count() + 1);
    }
}
