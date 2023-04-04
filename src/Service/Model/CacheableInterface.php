<?php

namespace Base\Service\Model;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

interface CacheableInterface
{
    public function __toKey(string ...$context): string;
    public function __toKeyTTL() : ?int;
    public function __toKeyTags() : array;
}
