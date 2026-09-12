<?php

namespace Base\Service;

use Symfony\Component\HttpFoundation\Request;

/**
 * Tells a speculative request - one a client made ahead of any user
 * action, hoping to have the answer ready - from a real one.
 *
 * Browsers announce prefetches and prerenders through request headers:
 * `Sec-Purpose: prefetch` (Fetch standard), `Purpose: prefetch` (Chrome,
 * Safari, prefetching proxies), `X-Moz: prefetch` (Firefox) and
 * `X-Purpose: preview` (Safari's Top Sites). Anything with a side effect -
 * signing out, counting a view - must treat such a request as if the user
 * had never asked for the page, because they have not.
 */
final class SpeculativeRequest
{
    private const HEADERS = ["Sec-Purpose", "Purpose", "X-Purpose", "X-Moz"];
    private const MARKERS = ["prefetch", "prerender", "preview"];

    public static function is(Request $request): bool
    {
        foreach (self::HEADERS as $header) {
            $value = strtolower((string) $request->headers->get($header, ""));
            if ($value === "") {
                continue;
            }

            foreach (self::MARKERS as $marker) {
                if (str_contains($value, $marker)) {
                    return true;
                }
            }
        }

        return false;
    }
}
