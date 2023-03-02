<?php

namespace Base\Service\Model\Obfuscator;

abstract class AbstractCompression implements CompressionInterface
{
    /**
     * @var CompressionInterface
     */
    protected $compression;

    protected int $level = -1;
    protected ?string $encoding = null;
    protected int $maxLength = 0;

    public function supports(string $name): bool
    {
        if(class_exists($name) && static::class == $name) return true;
        return $this->getName() == $name;
    }

    public function getLevel(): int { return $this->level; }
    public function setLevel(int $level)
    {
        $this->level = $level;
        return $this;
    }

    public function getEncoding(): ?string { return $this->encoding; }
    public function setEncoding(?string $encoding)
    {
        $this->encoding = $encoding;
        return $this;
    }

    public function getMaxLength(): int { return $this->maxLength; }
    public function setMaxLength(int $maxLength)
    {
        $this->maxLength = $maxLength;
        return $this;
    }

    abstract protected function encodeHex(string $hex): string|false;
    public function encode(string $data): ?string
    {
        $hex = bin2hex($this->encodeHex(bin2hex($data)));
        return $hex ? $hex : null;
    }

    abstract protected function decodeHex(string $data): string|false;
    public function decode(string $hex): ?string
    {
        try {

            $hex = hex2bin($this->decodeHex(hex2bin($hex)));
            if($hex === false) return null;

        } catch (\ErrorException $e) {

            return null;
        }

        return $hex === false ? null : $hex;
    }
}