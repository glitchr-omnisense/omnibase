<?php

namespace Base\Entity\Layout;

use Base\Entity\Layout\Attribute\Abstract\AbstractAttribute;

interface AttributeInterface
{
    public function getAdapter();
    public function setAdapter(?AbstractAttribute $adapter);

    public function getCode(): ?string; 
    public function getType(): ?string; 
    public function getOptions(): array;
    public function resolve(?string $locale = null): mixed;
}
