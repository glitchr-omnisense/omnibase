<?php

namespace Base\Imagine\Filter;

use Base\Imagine\FilterInterface;
use Imagine\Image\ImageInterface;

interface FormatFilterInterface extends FilterInterface
{
    public function getPath(): ?string;
    public function setPath(?string $path);

    public function getFilters();
    public function addFilter(FilterInterface $filter);

    public function apply(ImageInterface $image): ImageInterface;
}
