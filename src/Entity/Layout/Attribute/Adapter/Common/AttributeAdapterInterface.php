<?php

namespace Base\Entity\Layout\Attribute\Adapter\Common;

/**
 *
 */
interface AttributeAdapterInterface
{
    public static function getType(): string;

    public function getOptions(): array;

    public function resolve(mixed $value): mixed;
}
