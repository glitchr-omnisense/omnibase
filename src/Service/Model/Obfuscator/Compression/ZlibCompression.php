<?php

namespace Base\Service\Model\Obfuscator\Compression;

use Base\Service\Model\Obfuscator\AbstractCompression;

/**
 *
 */
class ZlibCompression extends AbstractCompression
{
    public function getName(): string
    {
        return "zlib";
    }

    public function getEncoding(): ?string
    {
        return $this->encoding ?? ZLIB_ENCODING_DEFLATE;
    }

    protected function encodeHex(string $hex): string|false
    {
        return gzcompress($hex, $this->getLevel(), $this->getEncoding());
    }

    protected function decodeHex(string $data): string|false
    {
        return gzuncompress($data, $this->getMaxLength());
    }
}
