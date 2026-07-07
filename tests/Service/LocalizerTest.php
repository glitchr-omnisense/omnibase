<?php

namespace Tests\Base\Service;

use Base\Service\Localizer;
use Base\Service\TranslatorInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;

class LocalizerTest extends TestCase
{
    /**
     * defaultLocale/fallbackLocales/locales/cacheLocales/isLate are all
     * static, shared across every Localizer instance in the process — reset
     * them so tests stay independent of run order (same convention as
     * SingletonTraitTest).
     */
    private function resetStatics(): void
    {
        $ref = new ReflectionClass(Localizer::class);
        $ref->setStaticPropertyValue('defaultLocale', null);
        $ref->setStaticPropertyValue('fallbackLocales', null);
        $ref->setStaticPropertyValue('locales', null);
        $ref->setStaticPropertyValue('cacheLocales', []);
        $ref->setStaticPropertyValue('isLate', null);
    }

    protected function setUp(): void
    {
        $this->resetStatics();
    }

    protected function tearDown(): void
    {
        $this->resetStatics();
    }

    /**
     * Seeds default/fallback locales directly via reflection: normalizeLocale()
     * and friends are all `public static`, so this is enough to exercise them
     * without going through the heavier instance construction (cache dir +
     * mocked ParameterBag/Translator) that warmUp() would otherwise need.
     */
    private function seedLocales(string $default, array $fallbacks): void
    {
        $ref = new ReflectionClass(Localizer::class);
        $ref->setStaticPropertyValue('defaultLocale', $default);
        $ref->setStaticPropertyValue('fallbackLocales', $fallbacks);
    }

    private function makeLocalizer(): Localizer
    {
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('getLocale')->willReturn('en-GB');

        return new Localizer($parameterBag, $translator, sys_get_temp_dir() . '/base-bundle-localizer-test-' . uniqid());
    }

    public function testGetLocalesGroupsCountriesByLanguage(): void
    {
        $locales = Localizer::getLocales();

        $this->assertArrayHasKey('en', $locales);
        $this->assertContains('GB', $locales['en']);
        $this->assertContains('US', $locales['en']);
        $this->assertArrayHasKey('fr', $locales);
        $this->assertContains('FR', $locales['fr']);
    }

    public function testToLocaleLangExtractsTheLanguage(): void
    {
        $this->assertSame('fr', Localizer::__toLocaleLang('fr-FR'));
    }

    public function testToLocaleLangFallsBackToTheDefaultForAnUnknownLanguage(): void
    {
        $this->seedLocales('en-GB', []);

        $this->assertSame('en', Localizer::__toLocaleLang('xx-XX'));
        $this->assertSame('en', Localizer::__toLocaleLang(''));
    }

    public function testToLocaleCountryPicksTheAvailableCountryForTheLanguage(): void
    {
        $this->seedLocales('en-GB', ['fr-FR', 'de-DE']);

        $this->assertSame('FR', Localizer::__toLocaleCountry('fr-FR'));
        $this->assertSame('GB', Localizer::__toLocaleCountry('en-GB'));
    }

    public function testToLocaleCountryFallsBackToTheFirstAvailableCountryWhenNoneIsGiven(): void
    {
        $this->seedLocales('en-GB', ['fr-FR']);

        // "fr" alone carries no country: falls back to the first country
        // available for French among the configured locales.
        $this->assertSame('FR', Localizer::__toLocaleCountry('fr'));
    }

    public function testToLocaleCombinesLangAndCountry(): void
    {
        $this->seedLocales('en-GB', ['fr-FR']);

        $this->assertSame('fr-FR', Localizer::__toLocale('fr-FR'));
        $this->assertSame('fr_FR', Localizer::__toLocale('fr-FR', '_'));
    }

    public function testNormalizeLocaleSwapsTheSeparator(): void
    {
        $this->seedLocales('en-GB', []);

        $this->assertSame('fr-FR', Localizer::normalizeLocale('fr_FR'));
        $this->assertSame('fr-FR', Localizer::normalizeLocale('fr-FR'));
    }

    public function testNormalizeLocaleCompletesABareLanguageCode(): void
    {
        $this->seedLocales('en-GB', ['fr-FR']);

        $this->assertSame('fr-FR', Localizer::normalizeLocale('fr'));
    }

    public function testNormalizeLocaleFallsBackToTheDefaultForAnUnknownLanguage(): void
    {
        $this->seedLocales('en-GB', ['fr-FR']);

        $this->assertSame('en-GB', Localizer::normalizeLocale('xx'));
    }

    public function testNormalizeLocaleMapsOverArrays(): void
    {
        $this->seedLocales('en-GB', []);

        $this->assertSame(['fr-FR', 'de-DE'], Localizer::normalizeLocale(['fr_FR', 'de_DE']));
    }

    public function testAvailableLocalesCombineDefaultAndFallbacks(): void
    {
        $this->seedLocales('en-GB', ['fr-FR', 'de-DE']);

        $this->assertSame(['en-GB', 'fr-FR', 'de-DE'], array_values(Localizer::getAvailableLocales()));
        $this->assertSame(['en', 'fr', 'de'], array_values(Localizer::getAvailableLocaleLangs()));
        $this->assertSame(['GB', 'FR', 'DE'], array_values(Localizer::getAvailableLocaleCountries()));
    }

    public function testDefaultAndFallbackAccessors(): void
    {
        $this->seedLocales('en-GB', ['fr-FR', 'de-DE']);

        $this->assertSame('en-GB', Localizer::getDefaultLocale());
        $this->assertSame('en', Localizer::getDefaultLocaleLang());
        $this->assertSame('GB', Localizer::getDefaultLocaleCountry());
        $this->assertSame(['fr-FR', 'de-DE'], Localizer::getFallbackLocales());
        $this->assertSame(['fr', 'de'], Localizer::getFallbackLocaleLangs());
        $this->assertSame(['FR', 'DE'], Localizer::getFallbackLocaleCountries());
    }

    public function testGetTimezoneDefaultsToUtc(): void
    {
        $this->seedLocales('en-GB', []);
        $localizer = $this->makeLocalizer();

        $this->assertSame('UTC', $localizer->getTimezone());
        $this->assertSame('UTC', Localizer::getDefaultTimezone());
    }

    public function testSetTimezoneRejectsAnUnknownIdentifier(): void
    {
        $this->seedLocales('en-GB', []);
        $localizer = $this->makeLocalizer();

        $localizer->setTimezone('Europe/Paris');
        $this->assertSame('Europe/Paris', $localizer->getTimezone());

        $localizer->setTimezone('Not/AZone');
        $this->assertSame('UTC', $localizer->getTimezone(), 'an invalid identifier must fall back to the default');
    }

    public function testSetCountryRejectsAnUnknownCode(): void
    {
        $this->seedLocales('en-GB', ['fr-FR']);
        $localizer = $this->makeLocalizer();

        $localizer->setCountry('FR');
        $this->assertSame('FR', $localizer->getCountry());

        $localizer->setCountry('ZZ');
        $this->assertSame('GB', $localizer->getCountry(), 'an invalid country code must fall back to the locale country');
    }

    /**
     * Current behavior: getCurrency()'s fallback, when no currency was ever
     * explicitly set, is getLocaleCountry() — a country code (e.g. "GB"),
     * not an actual currency code (e.g. "GBP"). setCurrency() itself does
     * validate against real ISO currency codes; only the fallback is off.
     */
    public function testGetCurrencyFallsBackToTheLocaleCountryNotAnActualCurrency(): void
    {
        $this->seedLocales('en-GB', []);
        $localizer = $this->makeLocalizer();

        $this->assertSame('GB', $localizer->getCurrency());

        $localizer->setCurrency('EUR');
        $this->assertSame('EUR', $localizer->getCurrency());

        $localizer->setCurrency('not-a-currency');
        $this->assertSame('USD', $localizer->getCurrency(), 'an invalid currency code must fall back to USD');
    }

    public function testGetLocaleNormalizesTheTranslatorLocale(): void
    {
        $this->seedLocales('en-GB', []);
        $localizer = $this->makeLocalizer();

        $this->assertSame('en-GB', $localizer->getLocale());
        $this->assertSame('fr-FR', $localizer->getLocale('fr_FR'), 'an explicit locale bypasses the translator');
    }

    public function testSetLocaleTracksWhetherTheLocaleActuallyChanged(): void
    {
        $this->seedLocales('en-GB', ['fr-FR']);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('getLocale')->willReturn('en-GB');
        $translator->expects($this->once())->method('setLocale')->with('fr_FR');

        $localizer = new Localizer(
            $this->createMock(ParameterBagInterface::class),
            $translator,
            sys_get_temp_dir() . '/base-bundle-localizer-test-' . uniqid()
        );

        $this->assertFalse($localizer->localeHasChanged());

        $localizer->setLocale('fr-FR');
        $this->assertTrue($localizer->localeHasChanged());
    }

    public function testSetLocalePropagatesToARequestWhenGiven(): void
    {
        $this->seedLocales('en-GB', []);
        $localizer = $this->makeLocalizer();
        $request = new Request();

        $localizer->setLocale('fr-FR', $request);

        $this->assertSame('fr_FR', $request->getLocale());
    }

    public function testMarkAsChangedIsFluentAndSticky(): void
    {
        $this->seedLocales('en-GB', []);
        $localizer = $this->makeLocalizer();

        $this->assertFalse($localizer->localeHasChanged());
        $this->assertSame($localizer, $localizer->markAsChanged());
        $this->assertTrue($localizer->localeHasChanged());
    }

    public function testMarkAsLateAndIsLate(): void
    {
        $this->assertFalse(Localizer::isLate());

        Localizer::markAsLate();
        $this->assertTrue(Localizer::isLate());
    }

    /**
     * The bug this locks in: compatibleLocale()'s $availableLocales param
     * defaults to null, but every call site fed it straight into in_array()
     * / array_search(), which require an array — omitting the third,
     * optional argument used to fatal with a TypeError on the very first
     * line. It must now default to getAvailableLocales() instead.
     */
    public function testCompatibleLocaleDoesNotFatalWithoutExplicitAvailableLocales(): void
    {
        $this->seedLocales('en-GB', ['fr-FR']);
        $localizer = $this->makeLocalizer();

        $this->assertIsBool($localizer->compatibleLocale('de-DE', 'fr-FR'));
    }

    public function testCompatibleLocaleIsFalseWhenTheLocaleIsAlreadyAvailable(): void
    {
        $this->seedLocales('en-GB', ['fr-FR']);
        $localizer = $this->makeLocalizer();

        $this->assertFalse($localizer->compatibleLocale('fr-FR', 'de-DE', ['en-GB', 'fr-FR']));
    }

    public function testCompatibleLocaleMatchesByLanguagePosition(): void
    {
        $this->seedLocales('en-GB', []);
        $localizer = $this->makeLocalizer();

        // Same position (index 0) in both the candidate's own list and the
        // available list once reduced to languages => compatible.
        $this->assertTrue($localizer->compatibleLocale('fr-CA', 'fr-FR', ['fr-FR', 'de-DE']));

        // Different language altogether => never compatible.
        $this->assertFalse($localizer->compatibleLocale('de-DE', 'fr-FR', ['fr-FR']));
    }
}
