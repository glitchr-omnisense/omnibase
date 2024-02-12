<?php

namespace Base\Entity\Layout\Attribute\Adapter\Common;

interface ScopeAdapterInterface extends AttributeAdapterInterface
{
    public function supports(mixed $value): bool;

    public function contains(mixed $value, mixed $subject): bool;
}
