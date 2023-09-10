<?php

namespace Base\Service\Model\Wysiwyg;

use Base\Imagine\FilterInterface;
use Base\Service\MediaServiceInterface;
use Base\Traits\BaseTrait;
use DOMDocument;

/**
 *
 */
class MediaEnhancer implements MediaEnhancerInterface
{
    use BaseTrait;

    /**
     * @var MediaServiceInterface
     */
    protected MediaServiceInterface $mediaService;

    public function __construct(MediaServiceInterface $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    public function enhance(string|array|null $strOrArray, array $config = [], FilterInterface|array $filters = [], array $attributes = []): string|array|null
    {
        if (!$strOrArray) {
            return $strOrArray;
        }

        $array = $strOrArray;
        if (!is_array($array)) {
            $array = [$array];
        }

        foreach($array as &$entry) {
    
            if(!$entry) continue;

            $entry = $this->mediaService->image($this->getPublicDir().$entry, $config, $filters);
        }

        return is_array($strOrArray) ? $array : first($array);
    }
}
