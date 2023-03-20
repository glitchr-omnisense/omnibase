<?php

namespace Base\Service\Model\Obfuscator\Compression;

use Base\Service\Model\Obfuscator\AbstractCompression;
use Hashids\Hashids;

class HashidsCompression extends AbstractCompression
{
    /**
     * @var Hashids
     */
    protected $hashids;
    public function __construct(string $secret)
    {
        if (class_exists(Hashids::class)) {
            $this->hashids = new Hashids($secret);
        }
    }

    public function getName(): string
    {
        return "hashids";
    }
    public function getEncoding(): ?string
    {
        return $this->encoding ?? ZLIB_ENCODING_GZIP;
    }

    public function isValid(string $hash): bool
    {
        return preg_match("/^[a-zA-Z0-9]+$/", $hash);
    }

    protected function encodeHex(string $hex): string|false
    {
        return $this->hashids->encodeHex($hex);
    }
    protected function decodeHex(string $data): string|false
    {
        if (!$this->isValid($data)) {
            return false;
        }
        return $this->hashids->decodeHex($data);
    }
}
