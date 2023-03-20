<?php

namespace Base\Entity\Layout\Attribute\Common;

interface RuleInterface extends AttributeInterface
{
    public function compliesWith(mixed $subject): bool;
}
