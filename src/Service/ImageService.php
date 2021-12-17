<?php

namespace Base\Service;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Data\DataManager;
use Liip\ImagineBundle\Imagine\Filter\FilterManager;

class ImageService
{
    private $cacheManager;
    private $dataManager;
    private $filterManager;

    public function __construct(CacheManager $cacheManager, DataManager $dataManager, FilterManager $filterManager) {
        $this->cacheManager  = $cacheManager;
        $this->dataManager   = $dataManager;
        $this->filterManager = $filterManager;
    }

    public function filter(int $width, int $height) {
        $filter = '...'; // Name of the `filter_set` in `config/packages/liip_imagine.yaml`
        $path = '...'; // Path of the image, relative to `/public/`
        
        if (!$this->cacheManager->isStored($path, $filter)) {
            $binary = $this->dataManager->find($filter, $path);

            $filteredBinary = $this->filterManager->applyFilter($binary, $filter, [
                'filters' => [
                    'thumbnail' => [
                        'size' => [$width, $height]
                    ]
                ]
            ]);

            $this->cacheManager->store($filteredBinary, $path, $filter);
        }
        return new RedirectResponse($this->cacheManager->resolve($path, $filter), Response::HTTP_MOVED_PERMANENTLY);
    }
}