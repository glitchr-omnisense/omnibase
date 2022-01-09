<?php

namespace Base\Filter;

use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;

class ImageFilter implements FilterInterface
{
    public function __construct(string $path, array $filters = [])
    {
        $this->path    = $path;
        $this->filters = $filters;
    }

    public function apply(ImageInterface $image): ImageInterface
    {
        foreach($this->filters as $filter)
            $image = $filter->apply($image);

        return $image->save($this->path);
    }
}