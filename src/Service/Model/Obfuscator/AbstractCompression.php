<?php

namespace Base\Service\Model\Obfuscator;

use ErrorException;

abstract class AbstractCompression implements CompressionInterface
{
    /**
     * @var CompressionInterface
     */
    protected CompressionInterface $compression;

    protected int $level = -1;
    protected ?string $encoding = null;
    protected int $maxLength = 0;

    public function supports(string $name): bool
    {
        if (class_exists($name) && static::class == $name) {
            return true;
        }
        return $this->getName() == $name;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    /**
     * @param int $level
     * @return $this
     */
    /**
     * @param int $level
     * @return $this
     */
    public function setLevel(int $level): static
    {
        $this->level = $level;
        return $this;
    }

    public function getEncoding(): ?string
    {
        return $this->encoding;
    }

    /**
     * @param string|null $encoding
     * @return $this
     */
    /**
     * @param string|null $encoding
     * @return $this
     */
    public function setEncoding(?string $encoding): static
    {
        $this->encoding = $encoding;
        return $this;
    }

    public function getMaxLength(): int
    {
        return $this->maxLength;
    }

    /**
     * @param int $maxLength
     * @return $this
     */
    /**
     * @param int $maxLength
     * @return $this
     */
    public function setMaxLength(int $maxLength): static
    {
        $this->maxLength = $maxLength;
        return $this;
    }

    abstract protected function encodeHex(string $hex): string|false;

    public function encode(string $data): ?string
    {
        $hex = $this->encodeHex(bin2hex($data));
        return $hex ?: null;
    }

    abstract protected function decodeHex(string $data): string|false;

    public function decode(string $hex): ?string
    {
        try {

            $hex = $this->decodeHex($hex);
            if ((strlen($hex) % 2) != 0 || !is_hex($hex)) {
                return null;
            }

            $bin = hex2bin($hex);
            if ($bin === false) {
                return null;
            }
            
        } catch (ErrorException $e) {
            return null;
        }

        return $bin ?: null;
    }
}
