<?php

namespace Base\Request;

/**
 * The page a request opens, as the request rules ask it (plan "omnibase agnostic", step F5e, slice 2): the
 * restriction's questions (Base\Auth\Policy\Page) and a few more, which only the host answers - its router
 * names the route, its request the address.
 */
interface Page extends \Base\Auth\Policy\Page
{
    /** A route of the account's own pages, "security_*" (omnibridge/symfony's AdvancedRouter::isSecured()). */
    public function isSecured(): bool;

    /** A route of the widgets' endpoints, "ux_*". */
    public function isUx(): bool;

    /** The whole address asked. */
    public function uri(): string;
}
