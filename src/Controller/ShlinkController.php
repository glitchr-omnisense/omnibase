<?php

namespace Base\Controller;

use Base\Repository\Layout\ShlinkRepository;
use Base\Routing\AdvancedRouterInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Base\Annotations\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Http\Discovery\Exception\NotFoundException;

/**
 *
 */
class ShlinkController extends AbstractController
{
    /**
     * @var ShlinkRepository
     */
    protected $shlinkRepository;

    /**
     * @var AdvancedRouterInterface
     */
    protected AdvancedRouterInterface $router;

    public function __construct(AdvancedRouterInterface $router, ShlinkRepository $shlinkRepository)
    {
        $this->router = $router;
        $this->shlinkRepository = $shlinkRepository;
    }

    #[Route("/{slug}", name: "shlink_redirect", subdomain: "s", priority: 1)]
    #[Route("/{slug}/{_locale}", name: "shlink_redirectByLocale", subdomain: "s", priority: 1)]
    public function Main(string $slug): Response
    {
        /**
         * @var Shlink $this
         */
        $shlink = $this->shlinkRepository->findOneBySlug($slug);
        if ($shlink === null) {
            throw new NotFoundException("Page requested doesn't exist.");
        }

        return $this->redirect($this->router->format($shlink->getUrl()));
    }
}
