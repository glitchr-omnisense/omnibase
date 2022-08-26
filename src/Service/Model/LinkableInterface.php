<?php

namespace Base\Service\Model;
 
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

interface LinkableInterface
{
    public function __toString();
    public function __toLink(array $routeParameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): ?string;
}
