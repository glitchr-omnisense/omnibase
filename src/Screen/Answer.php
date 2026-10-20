<?php

namespace Base\Screen;

/**
 * An action's answer as Negotiation gives it, before any framework (step R10
 * of the audit "lesringostars face au master plan"): its status, body and
 * headers, how it may be kept, whether the body is JSON (Symfony's
 * JsonResponse, Laravel's), or the file it is (sent as it is stored). Each
 * host's adapter makes its response of it, nothing more.
 */
final readonly class Answer
{
    /** @param array<string, string> $headers */
    public function __construct(
        public int $status = 200,
        public string $body = '',
        public array $headers = [],
        public Cache $cache = new Cache(),
        public bool $json = false,
        public ?string $file = null,
    ) {
    }
}
