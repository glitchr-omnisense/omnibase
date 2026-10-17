<?php

namespace Tests\Base\Twig;

use Base\Twig\Extension\FunctionTwigExtension;
use Omnibridge\Twig\HelperExtension;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * omnibridge/twig's HelperExtension is registered by omnibase, first: the
 * helpers it shares with omnibase's FunctionTwigExtension (html_attributes,
 * mb_ucfirst, second, join_if_exists, less_than) are its, and print what
 * omnibase's printed; the ones omnibase does more with (str_shorten,
 * highlight, datetime, lang, country, lang_name) stay omnibase's. Rendered by
 * the application's Twig, each beside the same template rendered by a bare
 * Twig with HelperExtension alone - what another framework prints.
 */
final class HelperExtensionTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }

        self::bootKernel();
    }

    protected function tearDown(): void
    {
        if (static::$booted) {
            static::getContainer()->get('translator')->setLocale('en');
        }
        parent::tearDown();
    }

    private function render(string $template, string $locale = 'fr', array $context = []): string
    {
        $container = static::getContainer();
        $request = Request::create('/');
        $request->setLocale($locale);
        $container->get('request_stack')->push($request);
        $container->get('translator')->setLocale($locale);

        return $container->get('twig')->createTemplate($template)->render($context);
    }

    private static function bare(string $template, string $locale = 'fr', array $context = []): string
    {
        $twig = new Environment(new ArrayLoader(['t' => $template]));
        $twig->addExtension(new HelperExtension($locale));

        return $twig->render('t', $context);
    }

    public function testTheSharedHelpersAreHelperExtensionsAndPrintTheSame(): void
    {
        $twig = static::getContainer()->get('twig');
        // html_decode too, unless the application registers its own (lesringostars does: the same result, and it wins).
        foreach (['mb_ucfirst', 'second', 'join_if_exists', 'less_than'] as $filter) {
            $callable = $twig->getFilter($filter)?->getCallable();
            self::assertInstanceOf(\Closure::class, $callable, $filter);
            self::assertSame(HelperExtension::class, (new \ReflectionFunction($callable))->getClosureScopeClass()?->getName(), $filter);
        }
        self::assertSame(HelperExtension::class, (new \ReflectionFunction($twig->getFunction('html_attributes')->getCallable()))->getClosureScopeClass()?->getName());

        $context = ['list' => ['a', 'b', 'c'], 'parts' => ['Lyon', null, '', 'France'], 'attr' => ['class' => ' a b ', 'data-x' => 'y', 'n' => 3]];
        foreach ([
            "{{ 'élan'|mb_ucfirst }}" => 'Élan',
            '{{ list|second }}' => 'b',
            "{{ parts|join_if_exists(', ') }}" => 'Lyon, France',
            "{{ html_attributes(attr) }}" => 'class="a b" data-x="y" n="3"',
            "{{ ('now'|date_modify('-1 day'))|less_than('+2 day') ? 'yes' : 'no' }}" => 'yes',
            "{{ ('now'|date_modify('-3 day'))|less_than('+2 day') ? 'yes' : 'no' }}" => 'no',
            "{{ 'L&#039;autre'|html_decode }}" => 'L&#039;autre', // escaped again on print, as everywhere
        ] as $template => $expected) {
            self::assertSame($expected, $this->render($template, 'fr', $context), $template);
            self::assertSame($expected, self::bare($template, 'fr', $context), $template.' (omnibridge/twig alone)');
        }

        // The PHP functions behind omnibase's old registrations print the same.
        self::assertSame(html_attributes($context['attr']), HelperExtension::htmlAttributes($context['attr']));
        self::assertSame(second($context['list']), HelperExtension::second($context['list']));
        self::assertSame(mb_ucfirst('élan'), HelperExtension::ucfirst('élan'));
    }

    public function testOmnibasesOwnStayOmnibases(): void
    {
        $twig = static::getContainer()->get('twig');
        foreach (['str_shorten', 'highlight', 'datetime'] as $filter) {
            $callable = $twig->getFilter($filter)->getCallable();
            $owner = \is_array($callable) ? (\is_object($callable[0]) ? $callable[0]::class : $callable[0]) : ($callable instanceof \Closure ? (new \ReflectionFunction($callable))->getClosureScopeClass()?->getName() : $callable);
            self::assertNotSame(HelperExtension::class, $owner, $filter);
        }
        self::assertSame(FunctionTwigExtension::class, $twig->getFilter('datetime')->getCallable()[0]::class);

        // What only omnibase's do: str_shorten's SHORTEN_BACK_SHRINK, datetime of a list of patterns.
        $text = 'Une phrase. Puis une autre qui dépasse la longueur demandée.';
        self::assertSame('Une phrase. [..]', $this->render('{{ text|str_shorten(20, 2) }}', 'fr', ['text' => $text]));
        self::assertSame('mars|2022', $this->render("{{ date|datetime(['MMMM', 'YYYY'])|join('|') }}", 'fr', ['date' => new \DateTime('2022-03-15 12:00:00')]));
    }

    public function testDatetimeIsLoweredAsOmnibridgeLowersIt(): void
    {
        $date = new \DateTime('2022-03-15 12:00:00');
        foreach ([
            ['MMMM YYYY', 'fr', 'mars 2022'],
            ["'on' dd MMMM YYYY", 'en', 'on 15 march 2022'],
            ['EEEE d MMMM', 'fr', 'mardi 15 mars'],
            ['YYYY-MM-dd HH:mm', 'fr', '2022-03-15 12:00'],
        ] as [$pattern, $locale, $expected]) {
            $template = "{{ date|datetime('".str_replace("'", "\\'", $pattern)."') }}";
            self::assertSame($expected, $this->render($template, $locale, ['date' => $date]), $pattern);
            self::assertSame($expected, self::bare($template, $locale, ['date' => $date]), $pattern.' (omnibridge/twig alone)');
        }
        // A word stays a word, lowered; nothing is the pattern.
        self::assertSame('présent', $this->render("{{ 'Présent'|datetime('MMMM YYYY') }}"));
        self::assertSame('présent', self::bare("{{ 'Présent'|datetime('MMMM YYYY') }}"));
        self::assertSame('MMMM YYYY', $this->render("{{ none|datetime('MMMM YYYY') }}", 'fr', ['none' => null]));
        self::assertSame('MMMM YYYY', self::bare("{{ none|datetime('MMMM YYYY') }}", 'fr', ['none' => null]));
    }
}
