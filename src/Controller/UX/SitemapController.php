<?php

namespace Base\Controller\UX;

use Base\Response\XmlResponse;
use Base\Service\SitemapperInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

use Base\Traits\BaseTrait;
use Symfony\Component\HttpFoundation\Request;

class SitemapController extends AbstractController
{
    use BaseTrait;

    /**
     * @Route("/sitemap.xml", name="ux_sitemap")
     */
    public function Main(Request $request, SitemapperInterface $sitemap): XmlResponse
    {
        $hostname = $request->getSchemeAndHttpHost();

        return $sitemap
            ->setHostname($hostname)
            ->registerAnnotations()
            ->generate('sitemap.xml.twig');
    }
}
