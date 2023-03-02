<?php

namespace Base\Entity\Layout\Attribute\Common;

interface ScopeInterface extends AttributeInterface
{
    public function contains(mixed $subject): bool;
}