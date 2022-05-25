<?php

namespace Base\Model;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

interface LinkableInterface
{
    public function __toString();
    public function __toLink(int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): ?string;
}
