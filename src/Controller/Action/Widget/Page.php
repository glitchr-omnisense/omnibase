<?php

namespace Base\Controller\Action\Widget;

use Base\Repository\Layout\Widget\PageRepository;
use Base\Screen\Template;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A page of the site's widgets (/page/{slug}: the legal notice, the privacy
 * policy...), written once for every host (step R27 of the audit
 * "lesringostars face au master plan"; formerly WidgetController::Page()):
 * widget/page.html.twig with the page, none of that slug not found.
 *
 * A host's routes read its #[Route]: Symfony's router (under src/Controller, which an
 * omnibase site's routes import, @BaseBundle/Controller), omnibridge/laravel's
 * Routing\ActionRoutes (this folder).
 */
#[Route('/page/{slug}', name: 'widget_page')]
final class Page
{
    public function __construct(private readonly PageRepository $pages)
    {
    }

    public function __invoke(string $slug): ?Template
    {
        $page = $this->pages->findOneBySlug($slug);

        return null === $page ? null : new Template('widget/page.html.twig', ['page' => $page]);
    }
}
