<?php

namespace Base\Filter;

use Imagine\Filter\Basic\WebOptimization;
use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;

class WebpFilter extends WebOptimization implements FilterInterface
{
    protected string $path;
    protected array $config = [];

    public function __construct(string $path, array $filters, array $config = [])
    {
        parent::__construct($path.".webp", $config);
        $this->filters = $filters;
    }

    public function apply(ImageInterface $image): ImageInterface
    {
        foreach($this->filters as $filter)
            $image = $filter->apply($image);

        return parent::apply($image);
    }
}