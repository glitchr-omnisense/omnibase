<?php

namespace Base\Imagine\Filter\Format;

use Base\Imagine\FilterInterface;
use Base\Imagine\Filter\Format\BitmapFilterInterface;
use Imagine\Filter\Basic\Autorotate;
use Imagine\Filter\Basic\WebOptimization;
use Imagine\Image\ImageInterface;

class WebpFilter extends WebOptimization implements BitmapFilterInterface
{
    protected array $filters;

    public function __toString()
    {
        $pathSuffixes = array_map(fn ($f) => is_stringeable($f) ? strval($f) : null, $this->filters);
        return path_suffix("", $pathSuffixes);
    }

    public function getFilters()
    {
        return $this->filters;
    }
    public function addFilter(FilterInterface $filter)
    {
        $this->filters[] = $filter;
        return $this;
    }

    public function __construct(?string $path = null, array $options = [], array $filters = [])
    {
        if (!$path) {
            $path = stream_get_meta_data(tmpfile())['uri'];
            unlink_tmpfile($path);
        }

        $this->path    = $path.".webp";
        $this->filters = $filters;

        if (array_key_exists("quality", $options)) {
            $options["quality"] *= $options["quality"] < 1 ? 100 : 1;
        }

        if ($options["autorotate"] ?? true) {
            array_prepend($this->filters, new Autorotate());
        }

        parent::__construct($this->path, $options);
    }

    protected string $path;
    public function getPath(): ?string
    {
        return $this->path;
    }
    public function setPath(?string $path)
    {
        $this->path = $path;
        return $this;
    }

    public function apply(ImageInterface $image): ImageInterface
    {
        foreach ($this->filters as $filter) {
            $oldImage = $image;
            try {
                $image = $filter->apply($oldImage);
            } catch (\Exception $e) {
                $image = $oldImage;
            }

            if (spl_object_id($image) != spl_object_id($oldImage)) {
                $oldImage->__destruct();
            }
        }

        $image = parent::apply($image);

        return $image;
    }
}
