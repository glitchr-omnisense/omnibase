<?php

namespace Base\Filter\Base;

use Base\Filter\LastFilterInterface;
use Imagine\Filter\Basic\WebOptimization;
use Imagine\Image\ImageInterface;

class WebpFilter extends WebOptimization implements LastFilterInterface
{
    protected array $config = [];

    public function __toString()
    {
        $pathSuffixes = array_map(fn($f) => is_stringeable($f) ? strval($f) : null, $this->filters);
        return path_suffix("", $pathSuffixes);
    }

    public function __construct(?string $path = null, array $filters = [], array $config = [])
    {
        $this->path    = $path.".webp";
        $this->filters = $filters;

        parent::__construct($this->path, $config);
    }

    protected string $path;
    public function getPath():?string { return $this->path; }
    public function setPath(?string $path) 
    {
        $this->path = $path;
        return $this;
    }

    public function apply(ImageInterface $image): ImageInterface
    {
        foreach($this->filters as $filter)
            $image = $filter->apply($image);

        return parent::apply($image);
    }
}