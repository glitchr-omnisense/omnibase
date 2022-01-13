<?php

namespace Base\Controller;

use Base\Component\HttpFoundation\XmlResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

use Base\Service\ImageService;
use Base\Traits\BaseTrait;
use Symfony\Component\HttpFoundation\Request;

class SitemapController extends AbstractController
{
    use BaseTrait;

    /**
     * @Route("/sitemap.xml", name="base_sitemap")
     */
    public function Sitemap(Request $request): XmlResponse
    {
        $urls = [];
        $hostname = $request->getSchemeAndHttpHost();
 
        // add static urls
        $urls[] = ['loc' => $this->generateUrl('home')];
        $urls[] = ['loc' => $this->generateUrl('contact_us')];
        $urls[] = ['loc' => $this->generateUrl('privacy_policy')];
         
        // add static urls with optional tags
        $urls[] = ['loc' => $this->generateUrl('fos_user_security_login'), 'changefreq' => 'monthly', 'priority' => '1.0'];
        $urls[] = ['loc' => $this->generateUrl('cookie_policy'), 'lastmod' => '2018-01-01'];
         
        // // add dynamic urls, like blog posts from your DB
        // foreach ($em->getRepository('BlogBundle:post')->findAll() as $post) {
        //     $urls[] = array(
        //         'loc' => $this->generateUrl('blog_single_post', array('post_slug' => $post->getPostSlug()))
        //     );
        // }
 
        // add image urls
        // $products = $em->getRepository('AppBundle:products')->findAll();
        // foreach ($products as $item) {
        //     $images = array(
        //         'loc' => $item->getImagePath(), // URL to image
        //         'title' => $item->getTitle()    // Optional, text describing the image
        //     );
 
        //     $urls[] = array(
        //         'loc' => $this->generateUrl('single_product', array('slug' => $item->getProductSlug())),
        //         'image' => $images              // set the images for this product url
        //     );
        // }
       
 
        // return response in XML format
        return new XmlResponse($this->renderView('sitemap/sitemap.html.twig', [
            'urls' => $urls, 
            'hostname' => $hostname
        ]));
    }
}