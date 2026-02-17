<?php

namespace Base\Service\Model\Wysiwyg;

use Base\Imagine\FilterInterface;
use Base\Service\FlysystemInterface;
use Base\Service\MediaServiceInterface;
use Base\Traits\BaseTrait;

class MediaEnhancer implements MediaEnhancerInterface
{
    use BaseTrait;

    /**
     * @var MediaServiceInterface
     */
    protected MediaServiceInterface $mediaService;

    /**
     * @var FlysystemInterface
     */
    protected FlysystemInterface $flysystem;

    public function __construct(MediaServiceInterface $mediaService, FlysystemInterface $flysystem)
    {
        $this->mediaService = $mediaService;
        $this->flysystem = $flysystem;
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
            if(array_key_exists("storage", $config)) {

                $storage = $config["storage"] ?? null;

                $array = $this->mediaService->resolve($entry);
                $_entry = $this->mediaService->image($path, $this->mediaService->resolve($entry), $storage);
 
                dump($_entry);
                dump($this->mediaService->resolve($_entry));
exit(1);
                if(file_exists($_entry)) {
                    $entry = $_entry;
                }
            }

            $entry = $this->mediaService->image($entry, $config, $filters);
        }

        return is_array($strOrArray) ? $array : first($array);
    }
}
