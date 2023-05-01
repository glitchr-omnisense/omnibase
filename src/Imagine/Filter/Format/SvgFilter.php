<?php

namespace Base\Imagine\Filter\Format;

use Base\Imagine\Filter\FormatFilterInterface;
use Base\Imagine\FilterInterface;

use Exception;
use Imagine\Image\Format;
use Imagine\Image\ImageInterface;
use Symfony\Component\Mime\MimeTypes;

class SvgFilter implements SvgFilterInterface
{
    protected array $filters;
    protected array $options;

    public function __toString()
    {
        $pathSuffixes = array_map(fn ($f) => is_stringeable($f) ? strval($f) : null, $this->filters);
        return path_suffix("", $pathSuffixes);
    }

    protected $mimeTypes;

    public function __construct(?string $path = null, array $options = [], array $filters = [])
    {
        if (!$path) {
            $path = stream_get_meta_data(tmpfile())['uri'];
            unlink_tmpfile($path);
        }

        $this->path    = $path;
        $this->filters = $filters;
        $this->options  = $options;

        $this->mimeTypes = new MimeTypes();
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

    protected ?string $path;
    public function getPath(): ?string
    {
        return $this->path;
    }
    public function setPath(?string $path)
    {
        $this->path = $path;
        return $this;
    }

    public function getExtension()
    {
        if ($this->path === null) {
            return null;
        }

        $mimeType = mime_content_type2($this->path);
        $extensions = $mimeType ? $this->mimeTypes->getExtensions($mimeType) : null;

        return in_array($this->options["extension"] ?? null, $extensions ?? []) ?? first($extensions);
    }

    public function apply(ImageInterface $image): ImageInterface
    {
        $mimeType = mime_content_type2($image->metadata()->get("filepath"));

        $extension = $this->getExtension() ?? $this->mimeTypes->getExtensions($mimeType)[0] ?? null;
        pathinfo_extension($this->path, $extension);

        foreach ($this->filters as $filter) {
            $oldImage = $image;
            try {
                $image = $filter->apply($oldImage);
            } catch (Exception $e) {
                $image = $oldImage;
            }

            if (spl_object_id($image) != spl_object_id($oldImage)) {
                $oldImage->__destruct();
            }
        }

        return $this->path === null ? $image : $image->save($this->path, $this->options);
    }
}
