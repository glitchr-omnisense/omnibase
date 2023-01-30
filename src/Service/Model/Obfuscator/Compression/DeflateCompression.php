<?php

namespace Base\Service\Model\Obfuscator\Compression;

use Base\Service\Model\Obfuscator\AbstractCompression;

class DeflateCompression extends AbstractCompression
{
    public function getName(): string { return "deflate"; }
    public function getEncoding(): ?string { return $this->encoding ?? ZLIB_ENCODING_RAW; }

    protected function encodeHex(string $data): string|false
    {
        return gzdeflate($data,  $this->getLevel(), $this->getEncoding());
    }
    protected function decodeHex(string $data): string|false
    {
        return gzinflate($data,  $this->getMaxLength());
    }
}