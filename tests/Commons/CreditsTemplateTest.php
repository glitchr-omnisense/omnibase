<?php

namespace Tests\Base\Commons;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * Glitch Art's signature (templates/partials/_credits.html.twig). `detail: false`
 * leaves the detail line out: `default(true)` read false as empty and showed it.
 * `open: true` shows it always, in the flow.
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

    public function testTheDetailFindsAFreeSideOncePerPage(): void
    {
        $html = self::render([]);

        $this->assertStringContainsString('window.gaCreditsSide || (window.gaCreditsSide =', $html);
        $this->assertStringContainsString("['below', 'above']", $html);
        $this->assertStringContainsString("addEventListener('pointerover', place)", $html);
        $this->assertStringContainsString("addEventListener('focusin', place)", $html);
    }

    /**
     * `open: true`: the detail always shown, in the flow beside the signature
     * (mirror puts it on the right as before). Its signature brings no script,
     * and the script another signature of the page brings leaves it alone.
     */
    public function testOpenShowsTheDetailWithoutTheScript(): void
    {
        $html = self::render(['open' => true, 'mirror' => true]);

        $this->assertStringContainsString('class="ga-credits ga-credits--black ga-credits--mirror ga-credits--open"', $html);
        $this->assertStringContainsString('EXAMPLE.ORG', $html);
        $this->assertStringNotContainsString('gaCreditsSide', $html);

        $closed = self::render([]);
        $this->assertStringContainsString('class="ga-credits ga-credits--black">', $closed);
        $this->assertStringContainsString("c.classList.contains('ga-credits--open')", $closed);
    }

    public function testDetailFalseLeavesItOut(): void
    {
        $html = self::render(['detail' => false]);

        $this->assertStringNotContainsString('gaCreditsSide', $html);

        $this->assertStringNotContainsString('ga-credits-detail', $html);
        $this->assertStringContainsString('ga-credits-monkey', $html);
    }
}
