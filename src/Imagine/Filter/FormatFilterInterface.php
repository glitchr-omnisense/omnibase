<?php

namespace Base\Imagine\Filter;

use Base\Imagine\FilterInterface;
use Imagine\Image\ImageInterface;

/**
 *
 */
interface FormatFilterInterface extends FilterInterface
{
    public static function getStandardExtension(): string;
    
    public function getPath(): ?string;

    /**
     * @param string|null $path
     * @return mixed
     */
    public function setPath(?string $path);

    /**
     * @return mixed
     */
    public function getFilters();

    /**
     * @param FilterInterface $filter
     * @return mixed
     */
    public function addFilter(FilterInterface $filter);

    public function apply(ImageInterface $image): ImageInterface;
}
