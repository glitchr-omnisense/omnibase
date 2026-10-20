<?php

namespace Tests\Base\Twig;

use Base\Service\ThemizerInterface;
use Base\Twig\Extension\MenuTwigExtension;
use Omnibridge\Contract\Locales;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * The language and theme menus on the host's Locales contract and the
 * ThemizerInterface alone (step R9 of the audit "lesringostars face au
 * master plan"): each a template rendered with the choices and the visitor's,
 * the options given laid under them - the same code Laravel registers.
 */
class MenuTwigExtensionTest extends KernelTestCase
{
    private static function themizer(): ThemizerInterface
    {
        return new class implements ThemizerInterface {
            public function getAvailableFilters(): array { return ['sepia' => ['id' => 'sepia'], 'invert' => ['id' => 'invert']]; }
            public function getFilter(): ?array { return ['id' => 'invert']; }
            public function getAvailableEvents(): array { return []; }
            public function getEvent(): ?array { return null; }
            public function getAvailableModes(): array { return ['auto' => ['id' => 'auto'], 'dark' => ['id' => 'dark']]; }
            public function getMode(): ?array { return ['id' => 'dark']; }
            public function color_scheme(): string { return 'dark'; }
            public function getAvailableWidths(): array { return ['wide' => ['id' => 'wide']]; }
            public function getWidth(): ?array { return ['id' => 'wide']; }
            public function width(): ?array { return ['id' => 'wide']; }
            public function getAudienceCategories(): array { return []; }
            public function getAudience(): ?array { return null; }
        };
    }

    public function testTheMenusAreRenderedWithTheChoicesAndTheVisitors(): void
    {
        $locales = new class implements Locales {
            public function getLocale(): string { return 'en-GB'; }
            public function getDefaultLocale(): string { return 'fr-FR'; }
            public function getAvailableLocales(): array { return ['fr-FR', 'en-GB']; }
        };
        // Each choice, the visitor's in brackets: "{{ class }}:a [b] ".
        $choices = static fn (string $available, string $current) => strtr('{{ class }}:{% for each in AVAILABLE %}{% set id = each.id ?? each %}{{ id == (CURRENT.id ?? CURRENT) ? "[" ~ id ~ "]" : id }} {% endfor %}', ['AVAILABLE' => $available, 'CURRENT' => $current]);
        $twig = new Environment(new ArrayLoader([
            'locale' => $choices('available_locales', 'current_locale'),
            'modes' => $choices('available_theme_modes', 'current_theme_mode'),
            'filters' => $choices('available_theme_filters', 'current_theme_filter'),
            'widths' => $choices('available_theme_widths', 'current_theme_width'),
            'page' => '{{ render_locale({class: "l"}, "locale") }}|{{ render_theme_modes({class: "m"}, "modes") }}|{{ render_theme_filters({class: "f"}, "filters") }}|{{ render_theme_widths({class: "w"}, "widths") }}',
        ]));
        $twig->addExtension(new MenuTwigExtension($locales, self::themizer()));

        self::assertSame('l:fr-FR [en-GB] |m:auto [dark] |f:sepia [invert] |w:[wide] ', $twig->render('page'));
    }

    /** Under Symfony the core registers it (omnibase's Localizer behind the Locales contract) and the menus are its, no longer LocalizerTwigExtension's nor ThemizerTwigExtension's. */
    public function testSymfonysMenusAreThisExtensions(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }
        self::bootKernel();
        $twig = static::getContainer()->get('twig');
        foreach (['render_locale', 'render_theme_modes', 'render_theme_filters', 'render_theme_widths'] as $name) {
            $callable = $twig->getFunction($name)?->getCallable();
            self::assertInstanceOf(\Closure::class, $callable, $name);
            self::assertInstanceOf(MenuTwigExtension::class, (new \ReflectionFunction($callable))->getClosureThis(), $name);
        }
    }
}
