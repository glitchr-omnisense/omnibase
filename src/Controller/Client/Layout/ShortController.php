<?php

namespace Base\Controller\Client\Layout;

use Base\Repository\Layout\ShortRepository;
use Base\Service\Settings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Http\Discovery\Exception\NotFoundException;

class ShortController extends AbstractController
{
    /**
     * @var ShortRepository
     */
    protected $shortRepository;

    public function __construct(Settings $settings, ShortRepository $shortRepository)
    {
        $this->settings = $settings;
        $this->shortRepository = $shortRepository;
    }

    /**
     * @Route("/{slug}", name="short_redirect", host="{host}", requirements={"host": "^([^\.]*)\.{0,1}s\.(.*)"}, defaults={"host": "{subdomain}.s.{domain}"}, priority=1)
     * @Route("/{slug}/{_locale}", name="short_redirectByLocale", host="{host}", requirements={"host": "^([^\.]*)\.{0,1}s\.(.*)"}, priority=1)
     */
    public function Main(string $slug): Response
    {
        /**
         * @var Short
         */
        $short = $this->shortRepository->findOneBySlug($slug);
        if($short === null) throw new NotFoundException("Page requested doesn't exist.");

        return $this->redirect($this->settings->url($short->getUrl()));
    }
}
