<?php

namespace Base\Screen;

/**
 * How an answer may be kept (step R10 of the audit "lesringostars face au
 * master plan"): by any cache (public) or by the visitor's browser alone
 * (private) - null leaves it to the host -, how long, whether it is to be
 * asked again once stale, by no cache at all (no-store), and the request
 * headers it varies on. Each host's adapter sets them on its response.
 */
final readonly class Cache
{
    /**
     * @param bool|null    $shared true: public, false: private, null: the host's default
     * @param list<string> $vary
     */
    public function __construct(
        public ?bool $shared = null,
        public ?int $maxAge = null,
        public bool $mustRevalidate = false,
        public bool $noStore = false,
        public array $vary = [],
    ) {
    }

    /** Kept by the visitor's browser alone. */
    public static function private(): self
    {
        return new self(shared: false);
    }

    /** Kept by any cache, that long. */
    public static function public(int $maxAge, bool $mustRevalidate = false): self
    {
        return new self(true, $maxAge, $mustRevalidate);
    }

    /**
     * A copy varying on these headers too, and private (and stored by no
     * cache) when asked so: what the answer adds to what the action said.
     *
     * @param list<string> $vary
     */
    public function with(array $vary = [], bool $private = false, bool $noStore = false): self
    {
        return new self(
            $private ? false : $this->shared,
            $this->maxAge,
            $this->mustRevalidate,
            $this->noStore || $noStore,
            array_values(array_unique([...$this->vary, ...$vary])),
        );
    }
}
