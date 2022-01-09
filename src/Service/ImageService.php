<?php

namespace Base\Service;

use Hashids\Hashids;
use Imagine\Filter\FilterInterface;
use Imagine\Image\ImagineInterface;
use League\FlysystemBundle\Lazy\LazyFactory;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Bridge\Twig\Extension\AssetExtension;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\MimeTypes;
use Twig\Environment;

class ImageService implements ImageServiceInterface
{
    public function __construct(Environment $twig, AssetExtension $assetExtension, ParameterBagInterface $parameterBag, ImagineInterface $imagine, FilesystemProvider $filesystemProvider)
    {
        $this->twig               = $twig;
        $this->imagine            = $imagine;
        $this->assetExtension     = $assetExtension;
        $this->filesystemProvider = $filesystemProvider;
        
        $this->hashIds = new Hashids($parameterBag->get("kernel.secret"));
        $this->mimeTypes = new MimeTypes();
    }

    protected $hashIds;
    public function encode(array $array): string { return $this->hashIds->encodeHex(bin2hex(serialize($array))); }
    public function decode(string $hash): array  { return unserialize(hex2bin($this->hashIds->decodeHex($hash))); }

    public function resolve(array|string|null $path, array $filters = [], $options = []): array|string|null
    {
        if(!$path) return $path;
        if(is_array($path)) return array_map(fn($p) => $this->resolve($p, $filters, $options), $path);

        $path = str_strip($path, $this->assetExtension->getAssetUrl(""));
        $options = array_merge($options, ["path" => $path, "filters" => $filters]);

        return $this->encode($options);
    }

    public function webp(array|string|null $path, array $filters = [], array $config = []): array|string|null { return $this->assetExtension->getAssetUrl("webp/").$this->resolve($path, $filters, $config); }
    public function thumbnail(array|string|null $path, array $filters = [], array $config = []): array|string|null { return $this->assetExtension->getAssetUrl("thumbnails/").$this->resolve($path, $filters, $config); }
    public function image(array|string|null $path, array $filters = [], array $config = []): array|string|null { return $this->assetExtension->getAssetUrl("images/").$this->resolve($path, $filters, $config); }
    public function imagify(null|array|string $path, array $filters = [], array $attributes = []) 
    {
        if(!$path) return $path;
        if(is_array($path)) return array_map(fn($p) => $this->imagify($p), $path);
        
        if(filter_var($path, FILTER_VALIDATE_URL) === FALSE)  return null;
        if($attributes["src"] ?? false)
            unset($attributes["src"]);

        return "<img ".html_attributes($attributes)." src='".$path."' />";
    }

    public function mimetype($fileOrArray) {

        if(is_array($fileOrArray)) return array_map(fn($f) => $this->mimetype($f), $fileOrArray);
        return $fileOrArray ? $this->mimeTypes->guessMimeType($fileOrArray) : null;
    }

    public function filter(string $path, array $filters = []): RedirectResponse
    {
        if(!$path) return null;

        $filters = array_filter($filters, fn($f) => class_implements_interface($f, FilterInterface::class));
        dump($filters);

        exit(1);
        return new RedirectResponse("XXXX", Response::HTTP_MOVED_PERMANENTLY);
        // if (!$this->cacheManager->isStored($path, $filterName)) {

        //     $binary = $this->filterManager->applyFilter(
        //         $this->dataManager->find($filterName, $path), 
        //         $filterName, $filters
        //     );

        //     $this->cacheManager->store($binary, $path, $filterName);
        // }

        // return new RedirectResponse(
        //                 $this->cacheManager->resolve($path, $filterName),
        //                 Response::HTTP_MOVED_PERMANENTLY
        //             );
    }
}