<?php

namespace Base\Filter;

use Imagine\Filter\FilterInterface;

interface LastFilterInterface extends FilterInterface
{
    public function getPath(): ?string;
    public function setPath(?string $path);
}
