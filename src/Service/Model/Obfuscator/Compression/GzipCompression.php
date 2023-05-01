<?php

namespace Base\Service\Model\Obfuscator\Compression;

use Base\Service\Model\Obfuscator\AbstractCompression;

/**
 *
 */
class GzipCompression extends AbstractCompression
{
    public function getName(): string
    {
        return "gzip";
    }

    public function getEncoding(): ?string
    {
        return $this->encoding ?? ZLIB_ENCODING_GZIP;
    }

    protected function encodeHex(string $hex): string|false
    {
        return gzencode($hex, $this->getLevel(), $this->getEncoding());
    }

    protected function decodeHex(string $data): string|false
    {
        return gzdecode($data, $this->getMaxLength());
    }
}
