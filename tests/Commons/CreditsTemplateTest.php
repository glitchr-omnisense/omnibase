<?php

namespace Tests\Base\Commons;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * Glitch Art's signature (templates/partials/_credits.html.twig). `detail: false`
 * leaves the detail line out: `default(true)` read false as empty and showed it.
 */
class CreditsTemplateTest extends TestCase
{
    private static function render(array $context): string
    {
        $twig = new Environment(new FilesystemLoader(\dirname(__DIR__, 2).'/templates'));
        $twig->addFunction(new TwigFunction('asset', fn (string $path) => '/'.$path));

        return $twig->render('partials/_credits.html.twig', $context + ['app' => ['request' => ['httpHost' => 'example.org'], 'site' => ['execution_time' => 0.12]]]);
    }

    public function testTheDetailLineIsShownByDefault(): void
    {
        $this->assertStringContainsString('ga-credits-detail', self::render([]));
        $this->assertStringContainsString('EXAMPLE.ORG', self::render(['detail' => true]));
    }

    public function testDetailFalseLeavesItOut(): void
    {
        $html = self::render(['detail' => false]);

        $this->assertStringNotContainsString('ga-credits-detail', $html);
        $this->assertStringContainsString('ga-credits-monkey', $html);
    }
}
