<?php

namespace Base\Contract;

/**
 * The token a form carries for an intent, against cross-site requests (plan
 * "omnibase agnostique", C5; decision D4: `csrf_token` enters the view
 * contract): what a page's POST sends back and its action checks. A screen
 * carries it in an action (Base\Screen\Account\Action) where a template
 * called `csrf_token()`.
 *
 * The Fondation's own contract, reached by Runtime::tokens() or given to a
 * screen's builder. Under Symfony, Base\Bridge\Symfony\CsrfTokens: the CSRF
 * token manager, so a token is the one Twig's `csrf_token()` printed and the
 * controller's isCsrfTokenValid() accepts. Laravel has one session token for
 * every intent: an action pointing at a Symfony route, rendered by Laravel,
 * would carry a token Symfony refuses (docs/20-architecture/contracts.md).
 */
interface Tokens
{
    /** The token of that intent ("2fa_disable", "passkey_delete_12"...) for the visitor's session. */
    public function csrf(string $intent): string;
}
