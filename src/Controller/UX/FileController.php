<?php

namespace Base\Controller\UX;

use Base\Annotations\Annotation\IsGranted;
use Base\Imagine\Filter\Basic\CropFilter;
use Base\Imagine\Filter\Format\BitmapFilter;
use Base\Imagine\Filter\Format\SvgFilter;
use Base\Imagine\Filter\Format\WebpFilter;
use Base\Repository\Layout\ImageCropRepository;
use Base\Service\FileService;
use Base\Service\Flysystem;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

use Base\Service\ImageService;
use Base\Traits\BaseTrait;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Profiler\Profiler;

/** @Route("", name="ux_", priority=-1) */
class FileController extends AbstractController
{
    use BaseTrait;

    /**
     * @var ImageService
     */
    protected $imageService;

    /**
     * @var FileService
     */
    protected $fileService;

    /**
     * @var Flysystem
     */
    protected $flysystem;

    /**
     * @var ImageCropRepository
     */
    protected $imageCropRepository;

    /**
     * @var Profiler
     */
    protected $profiler;

    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var ?bool
     */
    protected $localCache;

    public function __construct(RequestStack $requestStack, Flysystem $flysystem, ImageService $imageService, ImageCropRepository $imageCropRepository, ?Profiler $profiler = null, ?bool $localCache = null)
    {
        $this->imageCropRepository = $imageCropRepository;

        $this->imageService = $imageService;
        $this->profiler = $profiler;

        $this->fileService = cast($imageService, FileService::class);
        $this->flysystem   = $flysystem;

        $this->localCache    = $localCache ?? true;
        $this->requestStack = $requestStack;
    }

    /**
     * Returns a RedirectResponse to the given route with the given parameters.
     */
    public function redirectToRoute(string $route, array $parameters = [], int $status = 302): RedirectResponse
    {
        $isUX = str_starts_with($this->requestStack->getCurrentRequest()->get("_route"), "ux_");
        if ($this->profiler !== null && $isUX)
            $this->profiler->disable();

        $cacheless = $this->localCache == false ? "_cacheless" : "";
        return parent::redirectToRoute($route.$cacheless, $parameters, $status);
    }

    /**
     * @Route("/contents/{data}", name="serve", requirements={"data"=".+"})
     */
    public function Serve($data): Response
    {
        $config = $this->fileService->resolve($data);
        if(!array_key_exists("path", $config)) throw $this->createNotFoundException();

        $path     = $config["path"];

        $contents = $this->flysystem->read($path, $config["storage"] ?? null);
        if($contents === null) throw $this->createNotFoundException();

        $options = $config["options"];
        $options["attachment"] = $config["attachment"] ?? null;

        return $this->fileService->serveContents($contents, 200, $options);
    }

    /**
     * @Route("/images/cacheless/cropper/{identifier}/{data}/image.{extension}", name="imageCropExtension_cacheless", requirements={"data"=".+"})
     * @Route("/images/cacheless/cropper/{identifier}/{data}", name="imageCrop_cacheless", requirements={"data"=".+"})
     */
    public function ImageCropCacheless($data, string $identifier, string $extension = null): Response
    {
        $this->localCache = false;
        return $this->ImageCrop($data, $identifier, $extension);
    }

    /**
     * @Route("/images/cropper/{identifier}/{data}/image.{extension}", name="imageCropExtension", requirements={"data"=".+"})
     * @Route("/images/cropper/{identifier}/{data}", name="imageCrop", requirements={"data"=".+"})
     */
    public function ImageCrop($data, string $identifier, string $extension = null): Response
    {
        //
        // Extract parameters
        $config = $this->imageService->resolve($data);
        if(!array_key_exists("path", $config)) throw $this->createNotFoundException();

        $filters    = $config["filters"] ?? [];
        $options    = $config["options"] ?? [];
        $path       = $config["path"] ?? null;
        if(!$path) throw $this->createNotFoundException();

        // Redirect to proper path
        $extensions = $this->imageService->getExtensions($path);
        if(!$extensions) throw $this->createNotFoundException();
        if ($extension == null || !in_array($extension, $extensions))
            return $this->redirectToRoute("ux_imageCropExtension", ["data" => $data, "identifier" => $identifier, "extension" => first($extensions)], Response::HTTP_MOVED_PERMANENTLY);

        //
        // Get the most accurate cropping
        $uuid = basename($path);

        // Dimension information
        $imagesize = getimagesize($path);
        $naturalWidth = $imagesize[0] ?? 0;
        if($naturalWidth == 0) throw $this->createNotFoundException();
        $naturalHeight = $imagesize[1] ?? 0;
        if($naturalHeight == 0) throw $this->createNotFoundException();

        // Providing "label" information
        $imageCrop = $this->imageCropRepository->cacheOneBySlug($identifier, ["image.source" => $uuid]);

        // Providing just a "ratio" number
        if ($imageCrop === null && preg_match("/^(\d+|\d*\.\d+)$/", $identifier, $matches)) {

            $ratio = floatval($matches[1]);
            $ratio0 = $ratio/($naturalWidth/$naturalHeight);

            $imageCrop = $this->imageCropRepository->cacheOneByRatio0ClosestTo($ratio0, ["image.source" => $uuid], [], [], ["ratio0" => "e.width0/e.height0"])[0] ?? null;
        }

        // Providing a "width:height" information
        $width  = null;
        $height = null;
        if($imageCrop === null && preg_match("/([0-9]+)[:x]([0-9]+)/", $identifier, $matches)) {

            $width   = $matches[1];
            $width0  = $width/$naturalWidth;
            $height  = $matches[2];
            $height0 = $height/$naturalHeight;

            $ratio   = $height ? $width/$height : 0;
            $ratio0  = $width0/$height0;
            if($ratio0 == 0) throw $this->createNotFoundException();

            $imageCrop = $this->imageCropRepository->findOneByRatio0ClosestToAndWidth0ClosestToAndHeight0ClosestTo($ratio0, $width0, $height0, ["image.source" => $uuid], [], [], ["ratio0" => "e.width0/e.height0"])[0] ?? null;
            $identifier = $imageCrop->getWidth()."x".$imageCrop->getHeight();
        }

        //
        // Apply filter
        // NB: Only applying cropping if ImageCrop is found ..
        //     .. otherwise some naughty users might be generating infinite amount of image
        if($imageCrop) {

            array_prepend($filters, new CropFilter(
                $imageCrop->getX0(), $imageCrop->getY0(),
                $imageCrop->getWidth0(), $imageCrop->getHeight0()
            ));
        }

        // This has been removed, otherwise users might overload the server changing the size in the URL..
        // if($width && $height)
        //     array_prepend($filters, new ThumbnailFilter($height, $width));
        $localCache = array_pop_key("local_cache", $options);
        $localCache = $this->localCache ?? $config["local_cache"] ?? $localCache;

        // File should be access from default "image" route to spare some computing time
        // NB: These lines below are commented to keep the same url and cache the image
        // $config["identifier"] = $identifier;
        // $data = $this->imageService->obfuscate($path, $config, $filters);
        if($imageCrop === null) $identifier = "image";

        $output = pathinfo_extension($data."/".$identifier, $extension);
        $path = $this->imageService->filter($path, new BitmapFilter(null, $filters, $options), ["local_cache" => $localCache, "output" => $output]);

        $isUX = str_starts_with($this->requestStack->getCurrentRequest()->get("_route"), "ux_");
        return  $this->imageService->serve($path, 200, ["http_cache" => $path !== null, "profiler" => !$isUX]);
    }

    /**
     * @Route("/images/cacheless/{data}/image.webp", name="imageWebp_cacheless", requirements={"data"=".+"})
     */
    public function ImageWebpCacheless($data): Response
    {
        $this->localCache = false;
        return $this->ImageWebp($data);
    }

    /**
     * @Route("/images/debug/{data}/image.{extension}", name="debug_imageExtension", requirements={"data"=".+"})
     * @Route("/images/debug/{data}", name="debug_image", requirements={"data"=".+"})
     * @IsGranted("ROLE_EDITOR")
     */
    public function ImageDebug($data, string $extension = null): Response
    {
        return $this->Image($data, $extension, true);
    }

    /**
     * @Route("/images/cacheless/{data}/image.{extension}", name="imageExtension_cacheless", requirements={"data"=".+"})
     * @Route("/images/cacheless/{data}", name="image_cacheless", requirements={"data"=".+"})
     */
    public function ImageCacheless($data, string $extension = null): Response
    {
        $this->localCache = false;
        return $this->Image($data, $extension);
    }


    /**
     * @Route("/images/{data}/image.webp", name="imageWebp", requirements={"data"=".+"})
     */
    public function ImageWebp($data): Response
    {
        $config = $this->imageService->resolve($data);
        if(!array_key_exists("path", $config)) throw $this->createNotFoundException();

        $webp = $config["webp"] ?? $this->imageService->isWebpEnabled();
        if(!$webp) return $this->redirectToRoute("ux_image", ["data" => $data], Response::HTTP_MOVED_PERMANENTLY);

        $mimeType = $config["mimetype"] ?? $this->imageService->getMimeType($config["path"]);
        if($mimeType == "image/svg+xml") return $this->redirectToRoute("ux_imageSvg", ["data" => $data], Response::HTTP_MOVED_PERMANENTLY);

        $options = $config["options"];
        $filters = $config["filters"];

        $localCache = array_pop_key("local_cache", $options);
        $localCache = $this->localCache ?? $config["local_cache"] ?? $localCache;

        $output = pathinfo_extension($data."/image", "webp");
        $path = $this->imageService->filter($config["path"], new WebpFilter(null, $filters, $options), ["local_cache" => $localCache, "output" => $output]);

        $isUX = str_starts_with($this->requestStack->getCurrentRequest()->get("_route"), "ux_");
        return  $this->imageService->serve($path, 200, ["http_cache" => $path !== null, "profiler" => !$isUX]);
    }

    /**
     * @Route("/images/{data}/image.svg", name="imageSvg", requirements={"data"=".+"})
     */
    public function ImageSvg($data): Response
    {
        $config = $this->imageService->resolve($data);
        if(!array_key_exists("path", $config)) throw $this->createNotFoundException();

        $filters = $config["filters"];
        $options = $config["options"];

        $mimeType = $config["mimetype"] ?? $this->imageService->getMimeType($config["path"]);
        if($mimeType != "image/svg+xml")
            return $this->redirectToRoute("ux_image", ["data" => $data], Response::HTTP_MOVED_PERMANENTLY);

        $localCache = array_pop_key("local_cache", $options);
        $localCache = $this->localCache ?? $config["local_cache"] ?? $localCache;

        $output = pathinfo_extension($data."/image", "svg");
        $path = $this->imageService->filter($config["path"], new SvgFilter(null, $filters, $options), ["local_cache" => $localCache, "output" => $output]);

        $isUX = str_starts_with($this->requestStack->getCurrentRequest()->get("_route"), "ux_");
        return $this->imageService->serve($path, 200, ["http_cache" => $path !== null, "profiler" => !$isUX]);
    }

    /**
     * @Route("/images/{data}/image.{extension}", name="imageExtension", requirements={"data"=".+"})
     * @Route("/images/{data}", name="image", requirements={"data"=".+"})
     */
    public function Image($data, string $extension = null, bool $debug = false): Response
    {
        //
        // Extract parameters
        $config = $this->imageService->resolve($data);
        if(!array_key_exists("path", $config)) throw $this->createNotFoundException();

        $filters    = $config["filters"] ?? [];
        $options    = $config["options"] ?? [];
        $path       = $config["path"] ?? null;
        $identifier = $config["identifier"] ?? null;

        // If cropping identifier found
        if ($extension == null)
            return $this->redirectToRoute("ux_imageCrop", ["data" => $data, "identifier" => $identifier], Response::HTTP_MOVED_PERMANENTLY);

        // Redirect to proper path
        $extensions = $this->imageService->getExtensions($path);
        if(!$extensions) throw $this->createNotFoundException();
        if ($extension == null || !in_array($extension, $extensions))
            return $this->redirectToRoute("ux_imageExtension", ["data" => $data, "extension" => first($extensions)], Response::HTTP_MOVED_PERMANENTLY);

        $localCache = array_pop_key("local_cache", $options);
        $localCache = $this->localCache ?? $config["local_cache"] ?? $localCache;

        $output = pathinfo_extension($data."/image", $extension);
        $path = $this->imageService->filter($config["path"], new BitmapFilter(null, $filters, $options), ["local_cache" => $localCache, "output" => $output]);
        if($debug) {

            dump($data, $config, $path);
            exit(1);
        }

        $isUX = str_starts_with($this->requestStack->getCurrentRequest()->get("_route"), "ux_");
        return  $this->imageService->serve($path, 200, ["http_cache" => $path !== null, "profiler" => !$isUX]);
    }

}
