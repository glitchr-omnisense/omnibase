<?php

namespace Base\Screen;

/**
 * What a client asked of an action, as far as its answer depends on it
 * (step R10 of the audit "lesringostars face au master plan"): the address,
 * the types it accepts, and omnibridge/react's headers (X-Omnibridge: a
 * visit within a front end; X-Omnibridge-Version: the front end's version).
 * Each host's adapter reads them off its own request.
 */
final readonly class Visit
{
    /** The types a JSON answer may go by, as Symfony's Request names the format "json". */
    private const JSON = ['application/json', 'application/x-json'];

    public function __construct(
        public string $uri = '/',
        public string $accept = '',
        public ?string $frontEnd = null,
        public ?string $version = null,
    ) {
    }

    /**
     * The types of Accept, the preferred first: by quality, then in their
     * order (Symfony's AcceptHeader), without their parameters.
     *
     * @return list<string>
     */
    public function acceptable(): array
    {
        $types = [];
        foreach (explode(',', $this->accept) as $index => $item) {
            $parts = array_map('trim', explode(';', $item));
            $type = strtolower(array_shift($parts));
            if ('' === $type) {
                continue;
            }
            $quality = 1.0;
            foreach ($parts as $part) {
                if (str_starts_with(strtolower($part), 'q=')) {
                    $quality = (float) substr($part, 2);
                }
            }
            $types[] = [$type, $quality, $index];
        }
        usort($types, static fn (array $a, array $b): int => $a[1] === $b[1] ? $a[2] <=> $b[2] : $b[1] <=> $a[1]);

        return array_values(array_map(static fn (array $type): string => $type[0], $types));
    }

    /**
     * The client prefers JSON: the first type it accepts that names a format
     * (a wildcard names none) is JSON's - Symfony's getPreferredFormat()
     * "json", Laravel's wantsJson() for a type that comes first.
     */
    public function prefersJson(): bool
    {
        foreach ($this->acceptable() as $type) {
            if (str_ends_with($type, '/*')) {
                continue;
            }

            return \in_array($type, self::JSON, true);
        }

        return false;
    }

    /** The client asks JSON and not HTML at all (a script, not a browser). */
    public function asksJsonAlone(): bool
    {
        return str_contains($this->accept, 'application/json') && !str_contains($this->accept, 'text/html');
    }
}
