<?php

namespace Base\Controller;

use Base\Filter\Base\ImageFilter;
use Base\Filter\Base\WebpFilter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

use Base\Service\ImageService;
use Base\Traits\BaseTrait;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class ImageController extends AbstractController
{
    use BaseTrait;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    /**
     * @Route("/images/{hashid}.webp", name="base_image_webp")
     */
    public function ImageWebp(Request $request, $hashid): Response
    {
        return $this->redirectToRoute("base_webp", ["hashid" => $hashid], Response::HTTP_MOVED_PERMANENTLY);
    }
    
    /**
     * @Route("/images/{hashid}", name="base_image")
     */
    public function Image(Request $request, $hashid = null): Response
    {
        
        $args = $this->imageService->decode($hashid);
        $path = stream_get_meta_data(tmpfile())['uri'];

        return $this->imageService->filter($args["path"], [
            new ImageFilter($path, $args["filters"] ?? [], $args["options"] ?? [])
        ]);
    }

    /**
     * @Route("/webp/{hashid}", name="base_webp")
     */
    public function Webp(Request $request, $hashid = null): Response
    {
        if(!$this->imageService->isWebpEnabled())
            return $this->redirectToRoute("base_webp", ["hashid" => $hashid], Response::HTTP_MOVED_PERMANENTLY);

        $args = $this->imageService->decode($hashid);
        $path = stream_get_meta_data(tmpfile())['uri'];

        return $this->imageService->filter($args["path"], [
            new WebpFilter($path, $args["filters"] ?? [], $args["options"] ?? [])
        ]);
    }
}