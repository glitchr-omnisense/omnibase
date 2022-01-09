<?php

namespace Base\Service;

use Hashids\Hashids;
use Liip\ImagineBundle\Controller\ImagineController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Data\DataManager;
use Liip\ImagineBundle\Imagine\Filter\FilterManager;
use Liip\ImagineBundle\Templating\LazyFilterRuntime;
use Symfony\Bridge\Twig\Mime\WrappedTemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Translation\Exception\NotFoundResourceException;
use Twig\Environment;
use Twig\Error\LoaderError;

// https://symfony.com/bundles/LiipImagineBundle/current/filters.html
class ImageService
{
    protected $cacheManager;
    protected $dataManager;
    protected $filterManager;
 
    protected $hashIds;

    /**
     * @var LazyFilterRuntime
     */
    protected $lazyFilter;
    public function __construct(Environment $twig, ParameterBagInterface $parameterBag, ImagineController $imagineController, CacheManager $cacheManager, DataManager $dataManager, FilterManager $filterManager) {

        $this->twig              = $twig;

        $this->imagineController = $imagineController;
        $this->cacheManager      = $cacheManager;
        $this->dataManager       = $dataManager;
        $this->filterManager     = $filterManager;

        $this->hashIds = new Hashids($parameterBag->get("kernel.secret"));
        $this->lazyFilterRuntime = new LazyFilterRuntime($this->cacheManager);
    }
 
    public function webp(array|string|null $path, array $config = [], ?string $resolver = null, int $referenceType = UrlGeneratorInterface::ABSOLUTE_URL): array|string|null
    {
        if(!$path) return $path;
        if(is_array($path)) return array_map(fn($p) => $this->webp($p, $config), $path);

        return $path;
        return $this->image($path).".webp";
    }

    public function thumbnail(array|string|null $path, array $config = [], ?string $resolver = null, int $referenceType = UrlGeneratorInterface::ABSOLUTE_URL): array|string|null
    {
        if(!$path) return $path;
        if(is_array($path)) return array_map(fn($p) => $this->thumbnail($p, $config), $path);

        return $path;
        return "/thumbnail/".$this->image($path);
    }

    public function image(array|string|null $path, array $config = [], ?string $resolver = null, int $referenceType = UrlGeneratorInterface::ABSOLUTE_URL): array|string|null
    {
        if(!$path) return $path;
        if(is_array($path)) return array_map(fn($p) => $this->image($p, $config), $path);

        return $path;
        return "/images/".$path;
    }

    public function imagify(null|array|string $path, array $attributes = []) 
    { 
        if(!$path) return $path;
        if(is_array($path)) return array_map(fn($p) => $this->imagify($p), $path);
        
        if(filter_var($path, FILTER_VALIDATE_URL) === FALSE)  return null;
        if($attributes["src"] ?? false)
            unset($attributes["src"]);

        return "<img ".html_attributes($attributes)." src='".$path."' />";
    }

    public function filter(string $path, string $filterName, array $config = []): RedirectResponse
    {
        if(!$path) return null;
        if (!$this->cacheManager->isStored($path, $filterName)) {

            $binary = $this->filterManager->applyFilter(
                $this->dataManager->find($filterName, $path), 
                $filterName, $config
            );

            $this->cacheManager->store($binary, $path, $filterName);
        }

        return new RedirectResponse(
                        $this->cacheManager->resolve($path, $filterName),
                        Response::HTTP_MOVED_PERMANENTLY
                    );
    }
    
    public const SEPARATOR = ";";
    public function encode(array $array): string
    {
        $hex = bin2hex(serialize($array));
        return $this->hashIds->encodeHex($hex);
    }

    public function decode(string $hash): array
    {
        $hex = $this->hashIds->decodeHex($hash);
        $str = hex2bin($hex);

        return explode(self::SEPARATOR, $str);
    }
}