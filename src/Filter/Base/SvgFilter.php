<?php

namespace Base\Filter\Base;

use Base\Filter\LastFilterInterface;
use Base\Service\ImageService;
use Imagine\Image\ImageInterface;

class SvgFilter implements LastFilterInterface
{
    protected array $filters;
    protected array $options;
    
    public function __toString() { return $this->path; }
    public function __construct(?string $path = null, array $options = [])
    {
        $this->path    = $path;
        $this->options  = $options;
    }

    protected ?string $path;
    public function getExtension() { return $this->options["extension"] ?? ImageService::extension($this->path); }
    public function getPath():?string { return $this->path; }
    public function setPath(?string $path)
    {
        $this->path = $path;
        return $this;
    }

    public function apply(ImageInterface $image): ImageInterface
    {
        $extension = ImageService::extension($image->metadata()->get("filepath"));
        if( empty($this->getExtension()) ) $this->path .= $extension ? ".".$extension : "";

        return $this->path === null ? $image : $image->save($this->path, $this->options);
    }
}