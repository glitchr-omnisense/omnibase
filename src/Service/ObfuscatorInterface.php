<?php

namespace Base\Service;

interface ObfuscatorInterface
{
    function encode(array $value): string;
    function decode(string $hash): ?array;
}