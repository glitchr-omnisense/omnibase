<?php

namespace Base\Controller\Client\Layout;

use Base\Repository\Layout\ShortRepository;
use Base\Service\BaseSettings;
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

    public function __construct(BaseSettings $baseSettings, ShortRepository $shortRepository)
    {
        $this->baseSettings = $baseSettings;
        $this->shortRepository = $shortRepository;
    }

    /**
     * @Route("/{slug}", name="short_redirect", host="{host}", requirements={"host": "^s.(.*)"}, defaults={"host": "s.{subdomain}.{domain}"}, priority=1)
     * @Route("/{slug}/{_locale}", name="short_redirectByLocale", host="{host}", requirements={"host": "^s.(.*)"}, priority=1)
     */
    public function Main($slug): Response
    {
        /**
         * @var Short
         */
        $short = $this->shortRepository->findOneBySlug($slug);
        if($short === null) throw new NotFoundException("Page requested doesn't exist.");

        return $this->redirect($this->baseSettings->url($short->getUrl()));
    }
}
