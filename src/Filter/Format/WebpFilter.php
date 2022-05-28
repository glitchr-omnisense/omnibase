<?php

namespace Base\Filter\Format;

use Base\Filter\FilterInterface;
use Base\Filter\FormatFilterInterface;
use Imagine\Filter\Basic\WebOptimization;
use Imagine\Image\ImageInterface;

class WebpFilter extends WebOptimization implements FormatFilterInterface
{
    public function __toString()
    {
        $pathSuffixes = array_map(fn($f) => is_stringeable($f) ? strval($f) : null, $this->filters);
        return path_suffix("", $pathSuffixes);
    }

    public function getFilters() { return $this->filters; }
    public function addFilter(FilterInterface $filter) 
    {
        $this->filters[] = $filter;
        return $this;
    }

    public function __construct(?string $path = null, array $filters = [], array $options = [])
    {
        if(!$path) {
            $path = stream_get_meta_data(tmpfile())['uri'];
            unlink_tmpfile($path);
        }

        $this->path    = $path.".webp";
        $this->filters = $filters;

        parent::__construct($this->path, $options);
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