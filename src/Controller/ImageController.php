<?php

namespace Base\Controller;

use Base\Entity\Sitemap\Widget;
use Base\Repository\Sitemap\Widget\PageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;

use  Base\Service\BaseService;
use Doctrine\ORM\EntityManager;
use Exception;
use Http\Discovery\Exception\NotFoundException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToWriteFile;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Cache\CacheInterface;

class ImageController extends AbstractController
{
    public function __construct(PageRepository $pageRepository)
    {
        $this->pageRepository = $pageRepository;
    }

    /**
     * @Route("/images/{hashid}", name="base_image")
     */
    public function Image(Request $request, $hashid = null): Response
    {
        return new Response("IMAGE CONTROLLER !");
    }

    /**
     * @Route("/images/{hashid}.webp", name="base_image_webp")
     */
    public function ImageWebp(Request $request, $hashid = null): Response
    {
        return $this->redirect("/webp/{hashid}", Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * @Route("/thumbnails/{hashid}", name="base_thumbnail")
     */
    public function Thumbnail(Request $request, $hashid = null): Response
    {
        return new Response("IMAGE CONTROLLER !");
    }

    /**
     * @Route("/thumbnails/{hashid}.webp", name="base_thumbnail_webp")
     */
    public function ThumbnailWebp(Request $request, $hashid = null): Response
    {
        return $this->redirect("/thumbnails/{hashid}", Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * @Route("/webp/{hashid}", name="base_webp")
     */
    public function Webp(Request $request, $hashid = null): Response
    {
        return new Response("IMAGE CONTROLLER !");
    }
}