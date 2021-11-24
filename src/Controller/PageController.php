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

class PageController extends AbstractController
{
    public function __construct(PageRepository $pageRepository)
    {
        $this->pageRepository = $pageRepository;
    }

    /**
     * @Route("/page/{slug}", name="widget_page")
     */
    public function Page(Request $request, $slug = null): Response
    {
        $page = $this->pageRepository->findOneBySlug($slug);
        
        if($page === null)
            throw new NotFoundException("Page requested doesn't exist.");

        return $this->render("@Base/widget/page.html.twig", ["page" => $page]);
    }
}