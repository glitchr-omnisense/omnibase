<?php

namespace Base\Service;

/**
 *
 */
interface ObfuscatorInterface
{
    public const NO_SHORT  = false;
    public const USE_SHORT = true;

    public function encode(array $value, bool $short = Obfuscator::NO_SHORT): string;
    public function decode(string $hash, bool $short = Obfuscator::NO_SHORT): ?array;
}
