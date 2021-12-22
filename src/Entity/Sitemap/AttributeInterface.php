<?php

namespace Base\Entity\Sitemap;

interface AttributeInterface
{
    public static function getType(): string;
    public static function getOptions(): array;
    public function getValue(): mixed;
}