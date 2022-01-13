<?php

namespace Base\Controller\Client\Layout;

use Base\Repository\Layout\Widget\PageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Http\Discovery\Exception\NotFoundException;
use Symfony\Component\HttpFoundation\Request;

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