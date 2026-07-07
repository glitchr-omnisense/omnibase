<?php

namespace Base\Service\Model\Obfuscator\Compression;

use Base\Service\Model\Obfuscator\AbstractCompression;

class DeflateCompression extends AbstractCompression
{
    public function getName(): string
    {
        return "deflate";
    }

    public function getEncoding(): ?string
    {
        return $this->encoding ?? ZLIB_ENCODING_RAW;
    }

    protected function encodeHex(string $hex): string|false
    {
        return gzdeflate($hex, $this->getLevel(), $this->getEncoding());
    }

    protected function decodeHex(string $data): string|false
    {
        // Silenced: garbage input is an expected code path (any user-supplied
        // hash reaches this), signalled by the false return, not the warning.
        return @gzinflate($data, $this->getMaxLength());
    }
}
