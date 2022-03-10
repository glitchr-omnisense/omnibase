<?php

namespace Base\Controller\UX;

use Base\Filter\Base\ImageFilter;
use Base\Filter\Base\SvgFilter;
use Base\Filter\Base\WebpFilter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

use Base\Service\ImageService;
use Base\Traits\BaseTrait;
use Symfony\Component\HttpFoundation\Request;

/** @Route("/images", name="ux_") */
class ImageController extends AbstractController
{
    use BaseTrait;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    /**
     * @Route("/{hashid}", name="imagine")
     */
    public function Imagine($hashid): Response
    {
        $args = $this->imageService->decode($hashid);
        if(!$args) $args = [];

        if(!$this->imageService->isWebpEnabled())
            return $this->redirectToRoute("ux_imageExtension", ["hashid" => $hashid, "extension" => $this->imageService->getExtension($args["path"])], Response::HTTP_MOVED_PERMANENTLY);
        
        return $this->redirectToRoute("ux_webp", ["hashid" => $hashid], Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * @Route("/{hashid}/image.webp", name="webp")
     */
    public function Webp($hashid): Response
    {
        if(!$this->imageService->isWebpEnabled())
            return $this->redirectToRoute("ux_image", ["hashid" => $hashid], Response::HTTP_MOVED_PERMANENTLY);

        $path = stream_get_meta_data(tmpfile())['uri'];
        $args = $this->imageService->decode($hashid);
        if(!$args) $args = [];

        if(ImageService::mimetype($args["path"]) == "image/svg+xml")
            return $this->redirectToRoute("ux_svg", ["hashid" => $hashid], Response::HTTP_MOVED_PERMANENTLY);

        return $this->imageService->filter($args["path"] ?? null, [
            new WebpFilter($path, $args["filters"] ?? [], $args["options"] ?? [])
        ]);
    }
    
    /**
     * @Route("/{hashid}/image.svg", name="svg")
     */
    public function Svg($hashid): Response
    {
        $args = $this->imageService->decode($hashid);
        if(!$args) $args = [];

        $path = stream_get_meta_data(tmpfile())['uri'];
       
        $args = $this->imageService->decode($hashid);
        if(!$args) $args = [];

        return $this->imageService->filter($args["path"] ?? null, [
            new SvgFilter($path, $args["filters"] ?? [], $args["options"] ?? [])
        ]);
    }
    
    /**
     * @Route("/{hashid}/image", name="image")
     * @Route("/{hashid}/image.{extension}", name="imageExtension")
     */
    public function Image($hashid, string $extension = null): Response
    {
        $path = stream_get_meta_data(tmpfile())['uri'];
        $args = $this->imageService->decode($hashid);
        if(!$args) $args = [];

        $_extension = $this->imageService->getExtension($args["path"]);
        if($extension === null && $_extension != $extension)
            return $this->redirectToRoute("ux_imageExtension", ["hashid" => $hashid, "extension" => $_extension], Response::HTTP_MOVED_PERMANENTLY);

        return $this->imageService->filter($args["path"], [
            new ImageFilter($path, $args["filters"] ?? [], [])
        ]);
    }
}