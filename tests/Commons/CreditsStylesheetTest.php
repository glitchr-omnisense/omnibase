<?php

namespace Tests\Base\Commons;

use PHPUnit\Framework\TestCase;

/**
 * Glitch Art's signature (bundles/base/css/credits.css). Its detail line is
 * hidden by opacity, which keeps its width: set between the signature and the
 * edge it stands against, it pushed the signature that far from the edge
 * (about 140px on a site showing it on the left). Where the detail fades in
 * and out it holds no room; the font, its size and the two inks are untouched.
 */
class CreditsStylesheetTest extends TestCase
{
    private static function css(string $path): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2).'/'.$path);
    }

    public function testThePublishedStylesheetIsItsSource(): void
    {
        $this->assertSame(self::css('assets/styles/credits/credits.css'), self::css('public/css/credits.css'));
    }

    public function testTheHiddenDetailHoldsNoRoomWhereItFadesInAndOut(): void
    {
        $css = self::css('public/css/credits.css');

        $this->assertSame(1, preg_match('/@media \(hover: hover\) \{(.*?)\n\}/s', $css, $block), 'a block for pointers that hover');
        $this->assertMatchesRegularExpression('/\.ga-credits-detail \{[^}]*\bwidth: 0;/', $block[1]);
        $this->assertMatchesRegularExpression('/\.ga-credits \{ gap: 0; \}/', $block[1]);

        // Without a pointer it is always shown, and takes its place.
        $this->assertMatchesRegularExpression('/@media \(hover: none\) \{\s*\.ga-credits-detail \{ opacity: 1; \}/', $css);
    }

    /**
     * Where its side is taken (Nakaya's site map: a column of links on the
     * signature's left), the detail lay over the site's footer. The partial's
     * script then sets data-ga-credits-side, and the detail opens under or
     * over the signature, out of the flow, against the signature's own edge.
     */
    public function testTheDetailOpensUnderOrOverTheSignatureWhenItsSideIsTaken(): void
    {
        $css = self::css('public/css/credits.css');
        preg_match('/@media \(hover: hover\) \{(.*?)\n\}/s', $css, $block);

        $this->assertStringContainsString('.ga-credits[data-ga-credits-side] .ga-credits-detail { position: absolute; left: auto; right: 0; width: auto; }', $block[1]);
        $this->assertStringContainsString('.ga-credits--mirror[data-ga-credits-side] .ga-credits-detail { left: 0; right: auto; }', $block[1]);
        $this->assertStringContainsString('.ga-credits[data-ga-credits-side="below"] .ga-credits-detail { top: 100%;', $block[1]);
        $this->assertStringContainsString('.ga-credits[data-ga-credits-side="above"] .ga-credits-detail { bottom: 100%;', $block[1]);
        // Nothing moves: no transform, no transition but the fade.
        $this->assertDoesNotMatchRegularExpression('/translate|transform/', $css);
        preg_match_all('/transition: ([^;]+);/', $css, $transitions);
        $this->assertSame(['opacity .25s ease', 'none'], $transitions[1]);
    }

    /**
     * `open: true` (gitbud's footer): the detail always shown, in the flow beside
     * the signature, pointer or not - what a site wrote in its own stylesheet
     * (opacity, position: static, width). After the block for pointers that
     * hover, so that it wins there; nothing of the signature's face changes.
     */
    public function testAnOpenDetailIsAlwaysShownInTheFlow(): void
    {
        $css = self::css('public/css/credits.css');

        $this->assertStringContainsString('.ga-credits--open { gap: .75rem; }', $css);
        $this->assertSame(1, preg_match('/\.ga-credits\.ga-credits--open \.ga-credits-detail \{([^}]*)\}/', $css, $rule));
        foreach (['position: static;', 'width: auto;', 'opacity: 1;', 'pointer-events: auto;'] as $declaration) {
            $this->assertStringContainsString($declaration, $rule[1]);
        }
        $this->assertGreaterThan(strpos($css, '@media (hover: hover)'), strpos($css, '.ga-credits--open {'), 'after the block for pointers that hover');
        $this->assertDoesNotMatchRegularExpression('/font|color|#/', $rule[1]);
    }

    public function testTheSignatureKeepsItsFontAndItsTwoInks(): void
    {
        $css = self::css('public/css/credits.css');

        $this->assertStringContainsString('font-family: "Source Code Pro", ui-monospace, monospace;', $css);
        $this->assertStringContainsString('font-size: 18pt;', $css);
        $this->assertStringContainsString('line-height: 21px;', $css);
        $this->assertStringContainsString('--ga-credits-ink: #000;', $css);
        $this->assertStringContainsString('.ga-credits--white { --ga-credits-ink: #fff; }', $css);

        preg_match_all('/#[0-9a-fA-F]{3,8}\b/', $css, $colours);
        $this->assertSame(['#000', '#fff'], array_values(array_unique(array_map('strtolower', $colours[0]))), 'black and white only');
    }
}
