<?php

namespace Base\Entity\Layout\Attribute\Abstract;

interface AbstractAttributeInterface
{
    public static function getType(): string;
    public function getOptions(): array;

    public function resolve(mixed $value): mixed;
}