<?php

namespace Base\Entity\Sitemap\Attribute\Abstract;

interface AbstractAttributeInterface
{
    public static function getType(): string;
    public function getOptions(): array;

    public function getFormattedValue(string $value);
}