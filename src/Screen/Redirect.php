<?php

namespace Base\Screen;

/**
 * What an action hands back to send the client elsewhere (step R27 of the
 * audit "lesringostars face au master plan": a newsletter's tracking link
 * counts the click, then leads to its subject): the address, the status
 * (302 by default), stored by no cache by default.
 */
final readonly class Redirect
{
    public function __construct(
        public string $url,
        public int $status = 302,
        public Cache $cache = new Cache(),
    ) {
    }
}
