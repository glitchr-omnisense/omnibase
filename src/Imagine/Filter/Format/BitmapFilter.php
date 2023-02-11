<?php

namespace Base\Imagine\Filter\Format;

use Base\Imagine\FilterInterface;
use Base\Imagine\Filter\Format\BitmapFilterInterface;
use Imagine\Filter\Basic\Autorotate;
use Imagine\Image\ImageInterface;
use Symfony\Component\Mime\MimeTypes;

class BitmapFilter implements BitmapFilterInterface
{
    protected array $filters;
    protected array $options;

    public function __toString()
    {
        $pathSuffixes = array_map(fn($f) => is_stringeable($f) ? strval($f) : null, $this->filters);
        return path_suffix("", $pathSuffixes);
    }

    /**
     * @var MimeTypes
     */
    protected $mimeTypes;
    
    public function __construct(?string $path = null, array $filters = [], array $options = [])
    {
        if(!$path) {
            $path = stream_get_meta_data(tmpfile())['uri'];
            unlink_tmpfile($path);
        }

        $this->path    = $path;
        $this->filters = $filters;
        $this->options  = $options;

        if(array_key_exists("quality", $options))
            $options["quality"] /= $options["quality"] <= 1 ? 100 : 1;

        if($options["autorotate"] ?? true)
            array_prepend($this->filters, new Autorotate());

        $this->mimeTypes = new MimeTypes();
    }

    public function getFilters() { return $this->filters; }
    public function addFilter(FilterInterface $filter)
    {
        $this->filters[] = $filter;
        return $this;
    }

    protected ?string $path;
    public function getPath():?string { return $this->path; }
    public function setPath(?string $path)
    {
        $this->path = $path;
        return $this;
    }

    public function getExtension()
    {
        if($this->path === null) return null;

        $mimeType = mime_content_type2($this->path);
        $extensions = $mimeType ? $this->mimeTypes->getExtensions($mimeType) : null;

        return in_array($this->options["extension"] ?? null, $extensions ?? []) ?? first($extensions);
    }

    public function apply(ImageInterface $image): ImageInterface
    {
        foreach($this->filters as $filter){

            $oldImage = $image;
            $image = $filter->apply($oldImage);

            if(spl_object_id($image) != spl_object_id($oldImage))
                $oldImage->__destruct();
        }

        $image = $this->path === null ? $image : $image->save($this->path, $this->options);
        return $image;
    }
}