<?php

namespace Base\Entity\Layout\Attribute\Common;

interface ActionInterface extends AttributeInterface
{
    public function apply(mixed $subject): mixed;
}