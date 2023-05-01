<?php

namespace Base\Service\Model\Obfuscator;

interface CompressionInterface
{
    public function getName(): string;

    public function supports(string $name): bool;

    public function encode(string $data): ?string;

    public function decode(string $hex): ?string;
}
