<?php

namespace Base\Controller\UX;

use Base\BaseBundle;
use Base\Entity\Layout\ImageCrop;
use Base\Imagine\Filter\Basic\CropFilter;
use Base\Imagine\Filter\Format\BitmapFilter;
use Base\Imagine\Filter\Format\SvgFilter;
use Base\Imagine\Filter\Format\WebpFilter;
use Base\Repository\Layout\ImageCropRepository;
use Base\Service\FileService;
use Base\Service\Flysystem;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

use Base\Service\ImageService;
use Base\Traits\BaseTrait;
use Doctrine\ORM\EntityManagerInterface;

/** @Route("", name="ux_") */
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
     * @var ?bool
     */
    protected $localCache;

    public function __construct(Flysystem $flysystem, ImageService $imageService, EntityManagerInterface $entityManager, ?bool $localCache = null)
    {
        if(BaseBundle::hasDoctrine())
            $this->imageCropRepository = $entityManager->getRepository(ImageCrop::class);

        $this->imageService = $imageService;
        $this->fileService  = cast($imageService, FileService::class);
        $this->flysystem   = $flysystem;

        $this->localCache = $localCache;
    }

    /**
     * @Route("/contents/{hashid}", name="serve")
     */
    public function Serve($hashid): Response
    {
        $args = $this->fileService->resolve($hashid);
        if(!$args) throw $this->createNotFoundException();

        $path     = $args["path"];

        $contents = $this->flysystem->read($path, $args["storage"] ?? null);
        if($contents === null) throw $this->createNotFoundException();

        $options = $args["options"];
        $options["attachment"] = $args["attachment"] ?? null;

        return $this->fileService->serveContents($contents, 200, $options);
    }

    /**
     * @Route("/images/cacheless/{hashid}/image.webp", name="imageWebp_cacheless")
     */
    public function ImageWebpCacheless($hashid): Response
    {
        $this->localCache = false;
        return $this->ImageWebp($hashid);
    }

    /**
     * @Route("/images/{hashid}/image.webp", name="imageWebp")
     */
    public function ImageWebp($hashid): Response
    {
        $args = $this->imageService->resolve($hashid);
        if(!$args) throw $this->createNotFoundException();

        $webp = $args["webp"] ?? $this->imageService->isWebpEnabled();
        if(!$webp) return $this->redirectToRoute("ux_image", ["hashid" => $hashid], Response::HTTP_MOVED_PERMANENTLY);

        $mimeType = $args["mimetype"] ?? $this->imageService->getMimeType($args["path"]);
        if($mimeType == "image/svg+xml") return $this->redirectToRoute("ux_imageSvg", ["hashid" => $hashid], Response::HTTP_MOVED_PERMANENTLY);

        $options = $args["options"];
        $filters = $args["filters"];

        $localCache = array_pop_key("local_cache", $options);
        $localCache = $this->localCache ?? $args["local_cache"] ?? $localCache;

        $path = $this->imageService->filter($args["path"], new WebpFilter(null, $filters, $options), ["local_cache" => $localCache]);
        return  $this->imageService->serve($path, 200, ["http_cache" => $path !== null]);
    }

    /**
     * @Route("/images/{hashid}/image.svg", name="imageSvg_cacheless")
     */
    public function ImageSvgCacheless($hashid): Response
    {
        $this->localCache = false;
        return $this->ImageSvg($hashid);
    }

    /**
     * @Route("/images/{hashid}/image.svg", name="imageSvg")
     */
    public function ImageSvg($hashid): Response
    {
        $args = $this->imageService->resolve($hashid);
        if(!$args) throw $this->createNotFoundException();

        $filters = $args["filters"];
        $options = $args["options"];

        $mimeType = $args["mimetype"] ?? $this->imageService->getMimeType($args["path"]);
        if($mimeType != "image/svg+xml")
            return $this->redirectToRoute("ux_image", ["hashid" => $hashid], Response::HTTP_MOVED_PERMANENTLY);

        $localCache = array_pop_key("local_cache", $options);
        $localCache = $this->localCache ?? $args["local_cache"] ?? $localCache;

        $path = $this->imageService->filter($args["path"], new SvgFilter(null, $filters, $options), ["local_cache" => $localCache]);
        return $this->imageService->serve($path, 200, ["http_cache" => $path !== null]);
    }

    /**
     * @Route("/images/cacheless/{hashid}", name="image_cacheless")
     * @Route("/images/cacheless/{hashid}/image.{extension}", name="imageExtension_cacheless")
     */
    public function ImageCacheless($hashid, string $extension = null): Response
    {
        $this->localCache = false;
        return $this->Image($hashid, $extension);
    }

    /**
     * @Route("/images/{hashid}", name="image")
     * @Route("/images/{hashid}/image.{extension}", name="imageExtension")
     */
    public function Image($hashid, string $extension = null): Response
    {
        //
        // Extract parameters
        $args = $this->imageService->resolve($hashid);
        if(!$args) throw $this->createNotFoundException();

        $filters = $args["filters"];
        $options = $args["options"];
        $path    = $args["path"];

        // Redirect to proper path
        $extensions = $this->imageService->getExtensions($path);
        if ($extension == null || !in_array($extension, $extensions))
            return $this->redirectToRoute("ux_imageExtension", ["hashid" => $hashid, "extension" => first($extensions)], Response::HTTP_MOVED_PERMANENTLY);

        $localCache = array_pop_key("local_cache", $options);
        $localCache = $this->localCache ?? $args["local_cache"] ?? $localCache;

        $path = $this->imageService->filter($args["path"], new BitmapFilter(null, $filters, $options), ["local_cache" => $localCache]);
        return  $this->imageService->serve($path, 200, ["http_cache" => $path !== null]);
    }

    /**
     * @Route("/images/cacheless/{identifier}/{hashid}", name="imageCrop_cacheless")
     * @Route("/images/cacheless/{identifier}/{hashid}/image.{extension}", name="imageCropExtension_cacheless")
     */
   public function ImageCropCacheless($hashid, string $identifier, string $extension = null): Response
   {
       $this->localCache = false;
       return $this->ImageCrop($hashid, $identifier, $extension);
   }

    /**
     * @Route("/images/{identifier}/{hashid}", name="imageCrop")
     * @Route("/images/{identifier}/{hashid}/image.{extension}", name="imageCropExtension")
     */
    public function ImageCrop($hashid, string $identifier, string $extension = null): Response
    {
        //
        // Extract parameters
        $args = $this->imageService->resolve($hashid);
        if(!$args) throw $this->createNotFoundException();

        $filters = $args["filters"];
        $options = $args["options"];
        $path    = $args["path"];

        // Redirect to proper path
        $extensions = $this->imageService->getExtensions($path);
        if ($extension == null || !in_array($extension, $extensions))
            return $this->redirectToRoute("ux_imageCropExtension", ["hashid" => $hashid, "identifier" => $identifier, "extension" => first($extensions)], Response::HTTP_MOVED_PERMANENTLY);

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
        $imageCrop = $this->imageCropRepository->findOneBySlug($identifier, [], ["image.source" => $uuid]);

        // Providing just a "ratio" number
        if ($imageCrop === null && preg_match("/^(\d+|\d*\.\d+)$/", $identifier, $matches)) {

            $ratio = floatval($matches[1]);
            $ratio0 = $ratio/($naturalWidth/$naturalHeight);

            $imageCrop = $this->imageCropRepository->findOneByRatio0ClosestTo($ratio0, ["ratio0" => "e.width0/e.height0"], ["image.source" => $uuid])[0] ?? null;
        }

        // Providing a "width:height" information
        if($imageCrop === null && preg_match("/([0-9]*)[:x]([0-9]*)/", $identifier, $matches)) {

            $width   = $matches[1];
            $width0  = $width/$naturalWidth;
            $height  = $matches[2];
            $height0 = $height/$naturalHeight;

            $ratio   = $height ? $width/$height : 0;
            $ratio0  = $width0/$height0;
            if($ratio0 == 0) throw $this->createNotFoundException();

            $imageCrop = $this->imageCropRepository->findOneByRatio0ClosestToAndWidth0ClosestToAndHeight0ClosestTo($ratio0, $width0, $height0, ["ratio0" => "e.width0/e.height0"], ["image.source" => $uuid])[0] ?? null;
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

        $localCache = array_pop_key("local_cache", $options);
        $localCache = $this->localCache ?? $args["local_cache"] ?? $localCache;

        $path = $this->imageService->filter($path, new BitmapFilter(null, $filters, $options), ["local_cache" => $localCache]);
        return  $this->imageService->serve($path, 200, ["http_cache" => $path !== null]);
    }
}
