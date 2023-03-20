<?php

namespace Base\Service\Model\Obfuscator\Compression;

use Base\Service\Model\Obfuscator\AbstractCompression;

class NullCompression extends AbstractCompression
{
    public function getName(): string
    {
        return "null";
    }
    protected function encodeHex(string $hex): string
    {
        return $hex;
    }
    protected function decodeHex(string $data): string
    {
        return $data;
    }
}
