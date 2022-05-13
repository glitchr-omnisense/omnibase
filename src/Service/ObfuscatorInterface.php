<?php

namespace Base\Service;

interface ObfuscatorInterface
{
    function encode(mixed $value): string;
    function decode(string $hash): mixed;
}