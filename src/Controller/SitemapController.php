<?php

namespace Base\Controller;

use Base\Annotations\Annotation\Sitemap;
use Base\Annotations\AnnotationReader;
use Base\Component\HttpFoundation\XmlResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

use Base\Service\ImageService;
use Base\Traits\BaseTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

class SitemapController extends AbstractController
{
    use BaseTrait;

    /**
     * @Route("/sitemap.xml", name="base_sitemap")
     */
    public function Sitemap(Request $request, AnnotationReader $annotationReader, RouterInterface $router): XmlResponse
    {
        $urls = [];
        $hostname = $request->getSchemeAndHttpHost();

        foreach($router->getRouteCollection() as $name => $route)
        {
            $controller = $route->getDefault("_controller");
            if($controller === null) continue;

            list($class, $method) = explode("::", $controller);
            if(!class_exists($class)) continue;

            $annotations = $annotationReader->getAnnotationsFor($class, Sitemap::class, [AnnotationReader::TARGET_METHOD]);
            $annotations = $annotations[AnnotationReader::TARGET_METHOD][$class][$method] ?? [];

            $annotation = end($annotations);
            if(!$annotation) continue;

            $urls[] = [
                "loc" => $hostname.$router->generate($name, $route->getDefaults()),
                "priority" => $annotation->getPriority(),
                "lastmod" => $annotation->getLastMod(),
                "changefreq" => $annotation->getChangeFreq(),
            ];
        }

        // return response in XML format
        return new XmlResponse($this->renderView('sitemap.xml.twig', [
            'urls' => $urls, 
            'hostname' => $hostname
        ]));
    }
}