<?php

namespace Base\Controller\UX;

use Base\Imagine\Filter\Basic\CropFilter;
use Base\Imagine\Filter\Format\BitmapFilter;
use Base\Imagine\Filter\Format\SvgFilter;
use Base\Imagine\Filter\Format\WebpFilter;
use Base\Repository\Layout\ImageCropRepository;
use Base\Service\FileService;
use Base\Service\Filesystem;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

use Base\Service\ImageService;
use Base\Traits\BaseTrait;
use Exception;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

        $contents = $this->filesystem->read($path, $args["local_cache"] ?? null);
        if($contents === null) throw $this->createNotFoundException();

        $options = $args["options"];
        $options["attachment"] = $args["attachment"] ?? null;

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
        return  $this->imageService->serve($path, 200, ["http_cache" => $path !== null]);
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
        $path    = $args["path"];

        // Redirect to proper path
        $extensions = $this->imageService->getExtensions($path);
        if ($extension == null)
            return $this->redirectToRoute("ux_imageExtension", ["hashid" => $hashid, "extension" => first($extensions)], Response::HTTP_MOVED_PERMANENTLY);

        $path = $this->imageService->filter($args["path"], new BitmapFilter(null, $filters, $options), ["local_cache" => true]);
        return  $this->imageService->serve($path, 200, ["http_cache" => $path !== null]);
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
        $path = $args["path"];

        // Redirect to proper path
        $extensions = $this->imageService->getExtensions($path);
        if ($extension == null)
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
        if($imageCrop === null && preg_match("/([0-9]*):([0-9]*)/", $identifier, $matches)) {

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

            $filters[] = new CropFilter(
                $imageCrop->getX0(), $imageCrop->getY0(),
                $imageCrop->getWidth0(), $imageCrop->getHeight0()
            );
        }

        $path = $this->imageService->filter($path, new BitmapFilter(null, $filters, $options));
        return  $this->imageService->serve($path, 200, ["local_cache" => true, "http_cache" => $path !== null]);
    }
}
