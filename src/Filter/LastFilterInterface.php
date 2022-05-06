<?php

namespace Base\Filter;

interface LastFilterInterface extends FilterInterface
{
    public function getPath(): ?string;
    public function setPath(?string $path);
}
