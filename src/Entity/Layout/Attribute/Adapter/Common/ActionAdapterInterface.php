<?php

namespace Base\Entity\Layout\Attribute\Adapter\Common;

interface ActionAdapterInterface extends AttributeAdapterInterface
{
    public function apply(mixed $value, mixed $subject): mixed;
}
