<?php

namespace Base\Entity\Sitemap\Attribute;

interface AbstractAttributeInterface
{
    public static function getType(): string;
    public static function getOptions(): array;
}