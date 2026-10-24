<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A page at two addresses, with and without a segment of its own, as a site's
 * wishlist (/shop/wishlist, /shop/{store}/wishlist): path("demo_shelf", {store: null})
 * is /shop/shelf, never /shop//shelf, where requirements are not checked
 * (tests/Routing/EmptySegmentTest).
 */
#[Route(['en' => '/shop', 'fr' => '/boutique'], name: 'demo_')]
final class ShelfController extends AbstractController
{
    #[Route('/shelf', name: 'shelf.default')]
    #[Route('/{store}/shelf', name: 'shelf')]
    public function shelf(?string $store = null): Response
    {
        return new Response('shelf '.($store ?? '-'));
    }
}
