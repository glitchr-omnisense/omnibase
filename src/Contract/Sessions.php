<?php

namespace Base\Contract;

/**
 * The visitor's session, as an entity ends it (plan "omnibase agnostique",
 * C4b): User::logout() signs its user out when that user is the one signed in
 * - the session forgotten, the remember-me cookie cleared - and is told so;
 * otherwise it kicks the user out of the sessions it has elsewhere.
 *
 * The Fondation's own contract, reached by Runtime::sessions(). Under Symfony,
 * Base\Bridge\Symfony\SecuritySessions: Security's token storage and the
 * REMEMBERME cookie, on the path and on the site's domain.
 */
interface Sessions
{
    /** Signs the user out if it is the visitor signed in here; false when it is not (nobody, or someone else). */
    public function signOut(object $user): bool;
}
