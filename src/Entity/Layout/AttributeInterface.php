<?php

namespace Base\Entity\Layout;

use Base\Entity\Layout\Attribute\Abstract\AbstractAttribute;

interface AttributeInterface
{
    public function getAdapter();
    public function setAdapter(?AbstractAttribute $adapter): self;

    public function getCode(): ?string; 
    public function getType(): ?string; 
    public function getOptions(): array;
    
    public function get(?string $locale = null): mixed;
    public function set(...$args): self;
    public function resolve(?string $locale = null): mixed;
}
