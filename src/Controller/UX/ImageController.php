<?php

namespace Base\Controller\UX;

use Base\Filter\Advanced\CropFilter;
use Base\Filter\Base\ImageFilter;
use Base\Filter\Base\SvgFilter;
use Base\Filter\Base\WebpFilter;
use Base\Repository\Layout\ImageCropRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

use Base\Service\ImageService;
use Base\Traits\BaseTrait;

/** @Route("/images", name="ux_") */
class ImageController extends AbstractController
{
    use BaseTrait;

    public function __construct(ImageService $imageService, ImageCropRepository $imageCropRepository)
    {
        $this->imageService = $imageService;
        $this->imageCropRepository = $imageCropRepository;
    }

    /**
     * @Route("/{hashid}/image.webp", name="imageWebp")
     */
    public function ImageWebp($hashid): Response
    {
        if(!$this->imageService->isWebpEnabled())
            return $this->redirectToRoute("ux_image", ["hashid" => $hashid], Response::HTTP_MOVED_PERMANENTLY);

        $args = $this->imageService->resolveArguments($hashid);
        if(!$args) throw $this->createNotFoundException();

        $filters = $args["filters"];
        $options = $args["options"];
        $path = $args["path"];

        if(ImageService::mimetype($path) == "image/svg+xml")
            return $this->redirectToRoute("ux_imageSvg", ["hashid" => $hashid], Response::HTTP_MOVED_PERMANENTLY);

        $tmpfile = stream_get_meta_data(tmpfile())['uri'];
        $filter  = new WebpFilter($tmpfile, $filters, $options);

        return $this->imageService->filter($path, [$filter]);
    }
    
    /**
     * @Route("/{hashid}/image.svg", name="imageSvg")
     */
    public function ImageSvg($hashid): Response
    {
        $args = $this->imageService->resolveArguments($hashid);
        if(!$args) throw $this->createNotFoundException();

        $filters = $args["filters"];
        $options = $args["options"];
        $path = $args["path"];

        $tmpfile   = stream_get_meta_data(tmpfile())['uri'];
        $filter = new SvgFilter($tmpfile, $filters, $options);

        return $this->imageService->filter($path, [$filter]);
    }

    /**
     * @Route("/{hashid}", name="image")
     * @Route("/{hashid}/image.{extension}", name="imageExtension")
     */
    public function Image($hashid, string $extension = null): Response
    {
        $args = $this->imageService->resolveArguments($hashid);
        if(!$args) throw $this->createNotFoundException();

        $filters = $args["filters"];
        $options = $args["options"];
        $path = $args["path"];

        $args = $this->imageService->resolveArguments($hashid);
        if(!$args) throw $this->createNotFoundException();

        if($this->imageService->isWebpEnabled())
            return $this->redirectToRoute("ux_imageWebp", ["hashid" => $hashid], Response::HTTP_MOVED_PERMANENTLY);
        if($extension == null) 
            return $this->redirectToRoute("ux_imageExtension", ["hashid" => $hashid, "extension" => $this->imageService->getExtension($args["path"])], Response::HTTP_MOVED_PERMANENTLY);

        $tmpfile   = stream_get_meta_data(tmpfile())['uri'];
        $filter = new ImageFilter($tmpfile, $filters, $options);
        return $this->imageService->filter($path, [$filter]);
    }

    /**
     * @Route("/{identifier}/{hashid}", name="crop")
     * @Route("/{identifier}/{hashid}/image.{extension}", name="cropExtension")
     */
    public function Crop($hashid, string $identifier, string $extension = null): Response
    {
        //
        // Extract parameters
        $args = $this->imageService->resolveArguments($hashid);
        if(!$args) throw $this->createNotFoundException();

        $filters = $args["filters"];
        $options = $args["options"];
        $path = $args["path"];
        $uuid = basename($path);

        // Redirect to proper path
        $_extension = $this->imageService->getExtension($path);
        if($extension === null && $_extension != $extension)
            return $this->redirectToRoute("ux_cropExtension", ["hashid" => $hashid, "identifier" => $identifier, "extension" => $_extension], Response::HTTP_MOVED_PERMANENTLY);

        //
        // Get the most image cropping 
        $ratio = null;
        $imageCrop = $this->imageCropRepository->findOneByLabel($identifier);

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

        $tmpfile = stream_get_meta_data(tmpfile())['uri'];
        $filter  = new ImageFilter($tmpfile.".".$extension, $filters, $options);
        return $this->imageService->filter($path, [$filter], false);
    }
}
