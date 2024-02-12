<?php

namespace Base\Entity\Layout\Attribute\Common;

use Base\Entity\Layout\Attribute\Adapter\Common\AbstractAdapter;

interface AttributeInterface
{
    public function get(?string $locale = null): mixed;

    public function set(...$args): self;

    public function resolve(?string $locale = null): mixed;

    public function getAdapter();

    public function setAdapter(?AbstractAdapter $adapter): self;

    public function getCode(): ?string;

    public function getType(): ?string;

    public function getOptions(): array;
}
