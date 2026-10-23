<?php

namespace Tests\Base\Translation;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The welcome of the legacy administration (templates/admin-legacy/layout.html.twig)
 * is a key of the core's admin catalogue, in every site's words: the template
 * wrote one site's name ("des Ringo Stars") in French only.
 */
class AdminWelcomeTest extends KernelTestCase
{
    public function testTheWelcomeIsTheCataloguesInEachLanguage(): void
    {
        $translator = static::getContainer()->get(TranslatorInterface::class);

        self::assertSame("Bienvenue sur l'interface d'administration !", $translator->trans('welcome', [], 'admin', 'fr-FR'));
        self::assertSame('Welcome to the administration interface!', $translator->trans('welcome', [], 'admin', 'en-GB'));
    }

    public function testTheLayoutPrintsTheKeyAndNoSitesName(): void
    {
        $layout = (string) file_get_contents(\dirname(__DIR__, 2).'/templates/admin-legacy/layout.html.twig');

        self::assertStringContainsString("{{ '@admin.welcome'|trans }}", $layout);
        self::assertStringNotContainsString('Ringo Stars', $layout);
    }
}
