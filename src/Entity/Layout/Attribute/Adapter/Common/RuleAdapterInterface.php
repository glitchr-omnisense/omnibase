<?php

namespace Base\Entity\Layout\Attribute\Adapter\Common;

interface RuleAdapterInterface extends AttributeAdapterInterface
{
    public function supports(mixed $value): bool;
    public function compliesWith(mixed $value, mixed $subject): bool;
}
