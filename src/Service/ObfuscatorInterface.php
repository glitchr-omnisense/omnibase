<?php

namespace Base\Service;

/**
 *
 */
interface ObfuscatorInterface
{
    public function isShort(): bool;

    public function encode(array $value): string;
    public function decode(string $hash): ?array;
}
