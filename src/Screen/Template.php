<?php

namespace Base\Screen;

/**
 * A template and its data, what an action of a page hands back (step R10 of
 * the audit "lesringostars face au master plan"): the host renders it - Twig
 * under either framework -, with the status and the caching the action gives.
 *
 * - `json`: a client preferring JSON (Accept) gets the data itself, JSON-encoded,
 *   at the same address (both answers vary on Accept): a screen described once,
 *   read by a front end;
 * - `alone`: the page is the template's markup alone, nothing the host adds to
 *   its pages around it (under Symfony omnibase's tags: `_omnibase_inject: false`;
 *   under Laravel the site's page context).
 *
 * The data are the template's variables: an action leaves out what only a host
 * adds (Symfony's forms, D3) and the host adds it with with().
 */
final readonly class Template
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public string $template,
        public array $data = [],
        public int $status = 200,
        public bool $json = false,
        public bool $alone = false,
        public Cache $cache = new Cache(),
    ) {
    }

    /**
     * A copy with these variables added (or replaced), and another status.
     *
     * @param array<string, mixed> $data
     */
    public function with(array $data, ?int $status = null): self
    {
        return new self($this->template, $data + $this->data, $status ?? $this->status, $this->json, $this->alone, $this->cache);
    }
}
