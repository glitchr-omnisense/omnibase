<?php

namespace Base\Entity\Layout\Attribute\Abstract;

interface AbstractAttributeInterface
{
    public static function getType(): string;
    public function getOptions(): array;

    public function getFormattedValue(string $value);
}