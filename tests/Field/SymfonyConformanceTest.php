<?php

namespace Tests\Base\Field;

use Base\Field\Conformance\Samples;
use Base\Field\Conformance\Suite;
use Omnibridge\Symfony\Omnibase\Form\Conformance\SymfonyHost;
use Omnibridge\Symfony\Omnibase\Form\DefinitionForms;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * The field conformance suite on Symfony (plan "omnibase agnostic", step
 * F5d): every kind declared, every implemented kind drawn, shown, bound and
 * refused as its sample says, in French.
 */
class SymfonyConformanceTest extends KernelTestCase
{
    private ?string $locale = null;

    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }

        self::bootKernel();
        $request = Request::create('/');
        $request->setLocale('fr-FR');
        $request->setSession(static::getContainer()->get('session.factory')->createSession());
        static::getContainer()->get('request_stack')->push($request);
        static::getContainer()->get('translator')->setLocale('fr-FR');
        $this->locale = \Locale::getDefault();
        \Locale::setDefault('fr_FR');
    }

    protected function tearDown(): void
    {
        if (null !== $this->locale) {
            \Locale::setDefault($this->locale);
        }
        parent::tearDown();
    }

    private function host(): SymfonyHost
    {
        return new SymfonyHost(static::getContainer()->get(DefinitionForms::class), static::getContainer()->get('twig'));
    }

    public function testEveryKindIsDeclared(): void
    {
        self::assertSame([], Suite::coverage($this->host()));
    }

    /** @return iterable<string, array{string}> */
    public static function kinds(): iterable
    {
        foreach (array_keys(Samples::all()) as $kind) {
            yield $kind => [$kind];
        }
    }

    /** @dataProvider kinds */
    public function testTheKindConforms(string $kind): void
    {
        self::assertSame([], Suite::check($this->host(), Samples::all()[$kind]));
    }
}
