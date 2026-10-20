<?php

namespace Base\Screen;

/**
 * Data answered in JSON, what an action a page's script asks hands back
 * (step R10 of the audit "lesringostars face au master plan"): a feed, a
 * batch of comments, a token. A Template among the data (at any depth) is
 * rendered by the host into its markup, Fragments into theirs joined, before
 * the data are encoded - as Symfony's JsonResponse encodes them, under either
 * host.
 */
final readonly class Json
{
    public function __construct(
        public mixed $data,
        public int $status = 200,
        public Cache $cache = new Cache(),
    ) {
    }
}
