<?php

namespace Base\Controller\UX;

use Base\Filter\Basic\CropFilter;
use Base\Filter\Format\BitmapFilter;
use Base\Filter\Format\SvgFilter;
use Base\Filter\Format\WebpFilter;
use Base\Repository\Layout\ImageCropRepository;
use Base\Service\FileService;
use Base\Service\Filesystem;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

use Base\Service\ImageService;
use Base\Traits\BaseTrait;
use Exception;

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
     * @var Filesystem
     */
    protected $filesystem;
    
    /**
     * @var ImageCropRepository
     */
    protected $imageCropRepository;
    
    public function __construct(Filesystem $filesystem, ImageService $imageService, ImageCropRepository $imageCropRepository)
    {
        $this->imageCropRepository = $imageCropRepository;
        $this->imageService = $imageService;
        $this->fileService  = cast($imageService, FileService::class);
        $this->filesystem   = $filesystem;
    }

    /**
     * @Route("/contents/{hashid}", name="serve")
     */
    public function Serve($hashid): Response
    {
        $args = $this->fileService->resolve($hashid);
        if(!$args) throw $this->createNotFoundException();

        $path     = $args["path"];
        $mimeType = $args["mimetype"] ?? $this->imageService->getMimeType($path);
        if(preg_match("/image\/.*/", $mimeType))
            return $this->redirectToRoute("ux_image", ["hashid" => $hashid], Response::HTTP_MOVED_PERMANENTLY);

        $contents = $this->filesystem->read($path);
        if($contents === null) throw $this->createNotFoundException();

        $options = $args["options"];
        $options["attachment"] = $options["attachment"] ?? null;
        return $this->fileService->serveContents($contents, 200, $options);
    }

    /**
     * @Route("/images/{hashid}/image.webp", name="imageWebp")
     */
    public function ImageWebp($hashid): Response
    {
        if(!$this->imageService->isWebpEnabled())
            return $this->redirectToRoute("ux_image", ["hashid" => $hashid], Response::HTTP_MOVED_PERMANENTLY);

        $args = $this->imageService->resolve($hashid);
        if(!$args) throw $this->createNotFoundException();

        $mimeType = $args["mimetype"] ?? $this->imageService->getMimeType($args["path"]);
        if($mimeType == "image/svg+xml")
            return $this->redirectToRoute("ux_imageSvg", ["hashid" => $hashid], Response::HTTP_MOVED_PERMANENTLY);

        $options = $args["options"];
        $filters = $args["filters"];

        $path = $this->imageService->filter($args["path"], new WebpFilter(null, $filters, $options), ["local_cache" => true]);
        return $this->imageService->serve($path, 200, ["http_cache" => $path !== null]);
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

        $path = $this->imageService->filter($args["path"], new SvgFilter(null, $filters, $options), ["local_cache" => true]);
        return $this->imageService->serve($path, 200, ["http_cache" => $path !== null]);
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
        $path = $args["path"];

        // Redirect to proper path
        $extensions = $this->imageService->getExtensions($path);
        if(!$extensions) throw new Exception("Cannot determine image extension: \"$path\"");
        if ($extension == null)
            return $this->redirectToRoute("ux_imageExtension", ["hashid" => $hashid, "extension" => first($extensions)], Response::HTTP_MOVED_PERMANENTLY);

        $path = $this->imageService->filter($args["path"], new BitmapFilter(null, $filters, $options), ["local_cache" => true]);
        return $this->imageService->serve($path, 200, ["http_cache" => $path !== null]);
    }

    /**
     * @Route("/images/{identifier}/{hashid}", name="crop")
     * @Route("/images/{identifier}/{hashid}/image.{extension}", name="cropExtension")
     */
    public function Crop($hashid, string $identifier, string $extension = null): Response
    {
        //
        // Extract parameters
        $args = $this->imageService->resolve($hashid);
        if(!$args) throw $this->createNotFoundException();

        $filters = $args["filters"];
        $options = $args["options"];
        $path = $args["path"];

        // Redirect to proper path
        $extensions = $this->imageService->getExtensions($path);
        if(!$extensions) throw new Exception("Cannot determine image extension: \"$path\"");
        if ($extension == null)
            return $this->redirectToRoute("ux_cropExtension", ["hashid" => $hashid, "identifier" => $identifier, "extension" => first($extensions)], Response::HTTP_MOVED_PERMANENTLY);

        //
        // Get the most image cropping 
        $ratio = null;
        $uuid = basename($path);

        $imageCrop = $this->imageCropRepository->findOneBySlug($identifier, [], ["image.source" => $uuid]);

        // Providing just a number
        if ($imageCrop === null && preg_match("/^-?(?:\d+|\d*\.\d+)$/", $identifier, $matches))
            $imageCrop = $this->imageCropRepository->findOneByRatioClosestTo($ratio, ["ratio" => "e.width/e.height"], ["image.source" => $uuid])[0] ?? null;

        // Providing a ratio X:Y
        if($imageCrop === null && preg_match("/([0-9]*):([0-9]*)/", $identifier, $matches)) {
        
            $width  = (int) $matches[1];
            $height = (int) $matches[2];
            $ratio  = $width/$height;

            $imageCrop = $this->imageCropRepository->findOneByRatioClosestToAndWidthClosestToAndHeightClosestTo($ratio, $width, $height, ["ratio" => "e.width/e.height"], ["image.source" => $uuid])[0] ?? null;
        }
        
        //
        // Apply filter
        if($imageCrop) {
        
            $filters[] = new CropFilter(
                $imageCrop->getX(), $imageCrop->getY(), 
                $imageCrop->getWidth(), $imageCrop->getHeight()
            );
        }

        $path = $this->imageService->filter($path, new BitmapFilter(null, $filters, $options));
        return $this->imageService->serve($path, 200, ["local_cache" => true, "http_cache" => $path !== null]);
    }
}
