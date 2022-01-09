<?php

namespace Base\Controller;

use Base\Filter\ImageFilter;
use Base\Filter\ThumbnailFilter;
use Base\Filter\WebpFilter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

use Base\Service\ImageService;
use Symfony\Component\HttpFoundation\Request;

class ImageController extends AbstractController
{
    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    /**
     * @Route("/images/{hashid}", name="base_image")
     */
    public function Image(Request $request, $hashid = null): Response
    {
        $args = $this->imageService->decode($hashid);
        $args["filters"] = [new ImageFilter(...$args)];

        return  $this->imageService->filter(...$args);
    }

    /**
     * @Route("/thumbnails/{hashid}/{mode}/{resampling}", name="base_thumbnail")
     */
    public function Thumbnail(Request $request, $hashid = null): Response
    {
        $args = $this->imageService->decode($hashid);
        $args["filters"] = [new ThumbnailFilter(...$args)];

        return  $this->imageService->filter(...$args);
    }

    /**
     * @Route("/webp/{hashid}", name="base_webp")
     */
    public function Webp(Request $request, $hashid = null): Response
    {
        $args = $this->imageService->decode($hashid);
        $args["filters"] = [new WebpFilter(...$args)];

        return  $this->imageService->filter(...$args);
    }

    /**
     * @Route("/images/{hashid}.webp", name="base_image_webp")
     */
    public function ImageWebp(Request $request, $hashid = null): Response
    {
        return $this->redirect("/webp/".$hashid, Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * @Route("/thumbnails/{hashid}.webp", name="base_thumbnail_webp")
     */
    public function ThumbnailWebp(Request $request, $hashid = null): Response
    {
        return $this->redirect("/thumbnails/".$hashid, Response::HTTP_MOVED_PERMANENTLY);
    }
}