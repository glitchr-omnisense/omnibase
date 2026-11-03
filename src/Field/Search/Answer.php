<?php

namespace Base\Field\Search;

/**
 * What a remote search answers (RemoteSearch): its status and its JSON body - the page of
 * entries ({pagination, results}) or the reason it refused, a string, as omnibase's
 * AutocompleteController answered them. Each host sends it as its JSON response.
 */
final class Answer
{
    /** @param array<string, mixed>|string $body */
    public function __construct(public readonly int $status, public readonly array|string $body)
    {
    }

    /** @param array<string, mixed> $page */
    public static function page(array $page): self
    {
        return new self(200, $page);
    }

    public static function refused(string $reason, int $status = 500): self
    {
        return new self($status, $reason);
    }
}
