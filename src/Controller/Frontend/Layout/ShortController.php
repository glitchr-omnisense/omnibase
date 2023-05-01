<?php

namespace Base\Controller\Frontend\Layout;

use Base\Repository\Layout\ShortRepository;
use Base\Routing\RouterInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Base\Annotations\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Http\Discovery\Exception\NotFoundException;

/**
 *
 */
class ShortController extends AbstractController
{
    /**
     * @var ShortRepository
     */
    protected $shortRepository;
    private RouterInterface $router;

    public function __construct(RouterInterface $router, ShortRepository $shortRepository)
    {
        $this->router = $router;
        $this->shortRepository = $shortRepository;
    }

    /**
     * @Route("/{slug}", name="short_redirect", subdomain="s", priority=1)
     * @Route("/{slug}/{_locale}", name="short_redirectByLocale", subdomain="s", priority=1)
     */
    public function Main(string $slug): Response
    {
        /**
         * @var Short $this
         */
        $short = $this->shortRepository->findOneBySlug($slug);
        if ($short === null) {
            throw new NotFoundException("Page requested doesn't exist.");
        }

        return $this->redirect($this->router->format($short->getUrl()));
    }
}
