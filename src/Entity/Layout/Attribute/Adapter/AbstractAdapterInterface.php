<?php

namespace Base\Entity\Layout\Attribute\Adapter;

interface AbstractAdapterInterface
{
    public static function getType(): string;
    public function getOptions(): array;

    public function resolve(mixed $value): mixed;
}