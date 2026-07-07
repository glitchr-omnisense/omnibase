<?php

namespace Tests\Base\Service;

use Base\Enum\Quadrant\Quadrant;
use Base\Enum\Quadrant\Quadrant8;
use Base\Service\Localizer;
use Base\Service\ParameterBagInterface;
use Base\Service\Translator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator as SymfonyTranslator;

/**
 * Wraps a real Symfony Translator (ArrayLoader-backed, real catalogue lookups
 * and parameter substitution) instead of mocking every catalogue interaction
 * — the same "prefer a bare real dependency" approach used for SharerTest's
 * Twig ArrayLoader.
 */
class TranslatorTest extends TestCase
{
    private function resetLocalizerStatics(): void
    {
        $ref = new ReflectionClass(Localizer::class);
        $ref->setStaticPropertyValue('defaultLocale', null);
        $ref->setStaticPropertyValue('fallbackLocales', null);
    }

    protected function setUp(): void
    {
        $this->resetLocalizerStatics();
        $ref = new ReflectionClass(Localizer::class);
        $ref->setStaticPropertyValue('defaultLocale', 'en-GB');
        $ref->setStaticPropertyValue('fallbackLocales', ['fr-FR']);
    }

    protected function tearDown(): void
    {
        $this->resetLocalizerStatics();
    }

    /**
     * Resources are registered under the bare language code ("en", not
     * "en_GB") — matching how this bundle's own translation files are
     * actually named (messages+intl-icu.en.yaml, no country code). This
     * matters: Symfony's own region -> language fallback (e.g. "en_GB" ->
     * "en") only bridges cleanly when the real catalogue lives at the bare
     * level, which is what let us find a genuine bug — see
     * testProductionFallbackUsesTheDefaultLocaleTranslation's docblock.
     */
    private function makeTranslator(bool $isDebug = true): Translator
    {
        $symfony = new SymfonyTranslator('en');
        $symfony->addLoader('array', new ArrayLoader());
        $symfony->addResource('array', [
            'hello' => 'Hello!',
            'foo.bar' => 'FooBar value',
            'nested' => '{foo.bar}',
            'greet.hello' => 'Hi {name}!',
            'alias.key' => 'foo.bar',
            'base.years' => 'year(s)',
            'base.months' => 'month(s)',
            'base.days' => 'day(s)',
            'base.hours' => 'hour(s)',
            'base.minutes' => 'minute(s)',
            'base.seconds' => 'second(s)',
        ], 'en', 'messages');
        $symfony->addResource('array', [
            'widget.title' => 'A Widget',
        ], 'en', 'entities');
        $symfony->addResource('array', [
            'quadrant.north' => 'North',
        ], 'en', 'enums');

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('isDebug')->willReturn($isDebug);

        $translator = new Translator($symfony, $kernel, $this->createMock(ParameterBagInterface::class));
        $translator->setLocale('en');

        return $translator;
    }

    public function testLocaleAndFallbackLocalesDelegateToTheWrappedTranslator(): void
    {
        $translator = $this->makeTranslator();

        $this->assertSame('en', $translator->getLocale());
        $translator->setLocale('fr');
        $this->assertSame('fr', $translator->getLocale());

        $this->assertSame([], $translator->getFallbackLocales());
        $this->assertTrue($translator->getCatalogue('en')->has('hello'));
    }

    public function testTransExists(): void
    {
        $translator = $this->makeTranslator();

        $this->assertTrue($translator->transExists('hello'));
        $this->assertTrue($translator->transExists('@entities.widget.title'));
        $this->assertFalse($translator->transExists('missing.key'));
    }

    public function testTransQuietReturnsNullWhenMissing(): void
    {
        $translator = $this->makeTranslator();

        $this->assertSame('Hello!', $translator->transQuiet('hello'));
        $this->assertNull($translator->transQuiet('missing.key'));
    }

    public function testTransLooksUpAPlainNonDotId(): void
    {
        $this->assertSame('Hello!', $this->makeTranslator()->trans('hello'));
    }

    public function testTransLooksUpADotNotationId(): void
    {
        $this->assertSame('FooBar value', $this->makeTranslator()->trans('foo.bar'));
    }

    public function testTransResolvesAnAtDomainTag(): void
    {
        $this->assertSame('A Widget', $this->makeTranslator()->trans('@entities.widget.title'));
    }

    public function testTransSubstitutesBracketedParameters(): void
    {
        $this->assertSame('Hi World!', $this->makeTranslator()->trans('greet.hello', ['name' => 'World']));
    }

    /**
     * A dot-id whose catalogue value is itself the name of another
     * translatable key: the "nested translations" loop keeps resolving
     * until transExists() stops matching.
     */
    public function testTransFollowsAChainOfNestedTranslationKeys(): void
    {
        $this->assertSame('FooBar value', $this->makeTranslator()->trans('alias.key'));
    }

    /**
     * Current behavior: the "recursive" handling in trans() only re-resolves
     * dot/bracket structure found in the requested ID itself, not references
     * embedded inside an already-resolved translation VALUE. "nested"'s
     * catalogue value is the literal string "{foo.bar}", which is returned
     * as-is — it is not interpolated a second time.
     */
    public function testTransDoesNotInterpolateReferencesEmbeddedInAValue(): void
    {
        $this->assertSame('{foo.bar}', $this->makeTranslator()->trans('nested'));
    }

    public function testTransReturnsTheRawIdWhenNothingIsFoundInDebugMode(): void
    {
        $translator = $this->makeTranslator(isDebug: true);

        $this->assertSame('missing.key', $translator->trans('missing.key'));
        $this->assertSame('unknown_plain_id', $translator->trans('unknown_plain_id'));
    }

    /**
     * Current behavior, found by direct experimentation rather than reading
     * the code alone: outside debug mode, a totally-missing dot-id comes
     * back as "" rather than the raw id — even when no locale override was
     * requested. That's because the "fall back to the default locale"
     * check compares the (nullable) $locale parameter directly against
     * Localizer::getDefaultLocale(), and null is never == a non-empty
     * locale string — so the production-fallback branch fires on every
     * call that doesn't pass an explicit locale, not just genuine
     * locale mismatches. Documented here, not fixed: changing this would
     * alter what every already-live page shows for any missing translation.
     */
    public function testMissingTranslationsBecomeEmptyStringOutsideDebugMode(): void
    {
        $translator = $this->makeTranslator(isDebug: false);

        $this->assertSame('', $translator->trans('missing.key'));
    }

    /**
     * Outside debug mode, a dot-id missing from an explicitly-requested
     * locale falls back to the same key's translation in the default
     * locale rather than staying untranslated.
     */
    public function testProductionFallbackUsesTheDefaultLocaleTranslation(): void
    {
        $translator = $this->makeTranslator(isDebug: false);

        // "fr" has no catalogue at all, so this can only resolve through the
        // production fallback to the default locale ("en", bare — matching
        // Localizer::getDefaultLocale()'s "en-GB" normalized down to its
        // language fallback).
        $this->assertSame('FooBar value', $translator->trans('foo.bar', [], null, 'fr-FR'));
    }

    /**
     * Regression test for a real infinite loop found while designing the
     * test above: transExists() normalizes its locale to underscore form
     * before checking the catalogue, but trans()'s own lookups used to pass
     * the locale through unnormalized. A translation registered ONLY under
     * a region-specific key (unlike this bundle's real catalogues, which are
     * all bare-language) exposed the gap: transExists("en-GB") would find it
     * (it normalizes to "en_GB" first) while the direct lookup wouldn't (no
     * "en-GB" catalogue exists, and Symfony's own region fallback doesn't
     * bridge dash<->underscore siblings of the SAME region, only region ->
     * bare-language parents) — so $trans never changed and the "nested
     * translations" while loop spun forever. Fixed by normalizing once,
     * consistently, before any catalogue lookup.
     */
    public function testProductionFallbackDoesNotHangOnARegionSpecificOnlyTranslation(): void
    {
        $symfony = new SymfonyTranslator('en_GB');
        $symfony->addLoader('array', new ArrayLoader());
        $symfony->addResource('array', ['foo.bar' => 'FooBar value'], 'en_GB', 'messages');

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('isDebug')->willReturn(false);

        $translator = new Translator($symfony, $kernel, $this->createMock(ParameterBagInterface::class));
        $translator->setLocale('en_GB');

        $this->assertSame('FooBar value', $translator->trans('foo.bar', [], null, 'fr-FR'));
    }

    public function testTransRouteWrapsTheControllersDomain(): void
    {
        $translator = $this->makeTranslator(isDebug: true);

        // No "@controllers.foo.title" translation is defined, so the
        // dot-id/domain-tag fallback format is returned unresolved.
        $this->assertSame('@controllers.foo.title', $translator->transRoute('foo'));
        $this->assertFalse($translator->transRouteExists('foo'));
    }

    public function testTransTimeFormatsADuration(): void
    {
        $translator = $this->makeTranslator();

        // 90061s = 1 day, 1 hour, 1 minute, 1 second (no years/months).
        $this->assertSame('year(s)  month(s) 1 day(s) 1 hour(s) 1 minute(s) 1 second(s)', $translator->transTime(90061));
    }

    public function testTransTimeOfZeroIsEmpty(): void
    {
        $this->assertSame('', $this->makeTranslator()->transTime(0));
    }

    public function testParseClassExtendsWalksTheClassHierarchy(): void
    {
        $translator = $this->makeTranslator();

        $this->assertSame('quadrant.enum_type.type', $translator->parseClass(Quadrant::class, Translator::PARSE_EXTENDS));
        $this->assertSame('quadrant8.quadrant.enum_type.type', $translator->parseClass(Quadrant8::class, Translator::PARSE_EXTENDS));
    }

    public function testTransEnumTranslatesAPermittedValue(): void
    {
        $translator = $this->makeTranslator();

        $this->assertSame('North', $translator->transEnum(Quadrant::N, Quadrant::class));
    }

    public function testTransEnumExists(): void
    {
        $translator = $this->makeTranslator();

        $this->assertTrue($translator->transEnumExists(Quadrant::N, Quadrant::class));
        $this->assertFalse($translator->transEnumExists('bogus', Quadrant::class));
    }
}
