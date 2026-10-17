<?php

namespace Base\Service\Model;

use Base\Contract\Link;

/**
 * A thing with an address. $referenceType is one of Symfony's
 * UrlGeneratorInterface reference types, by their values (Base\Contract\Link's
 * constants, so that the interface names nothing of the routing component);
 * Link::to() makes the address through the Urls contract.
 */
interface LinkableInterface
{
    public function __toString();

    public function __toLink(array $routeParameters = [], int $referenceType = Link::ABSOLUTE_PATH): ?string;
}
