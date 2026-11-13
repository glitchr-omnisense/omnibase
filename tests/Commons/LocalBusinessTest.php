<?php

namespace Tests\Base\Commons;

use Base\Entity\Hours\SpecialDay;
use Base\Service\LocalBusiness;
use Base\Service\OpeningHours;
use Base\Service\SettingBagInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class LocalBusinessTest extends TestCase
{
    private const CONFIG = [
        'type' => 'LocalBusiness', 'name' => null, 'url' => null, 'telephone' => '03 00 00 00 00', 'email' => null,
        'image' => 'assets/ico/logo.png', 'price_range' => null,
        'address' => ['street' => '1 rue des Exemples', 'postal_code' => '67000', 'locality' => 'Strasbourg', 'region' => null, 'country' => 'FR'],
        'geo' => ['latitude' => 48.6, 'longitude' => 7.75],
        'same_as' => ['https://www.facebook.com/atelierexemple/'],
        'area_served' => ['Strasbourg'],
        'extra' => ['parentOrganization' => ['@type' => 'Organization', 'name' => 'Exemple Groupe']],
    ];

    /** @param array<string, mixed> $settings */
    private function business(array $settings = [], array $config = self::CONFIG): LocalBusiness
    {
        $bag = $this->createStub(SettingBagInterface::class);
        $bag->method('getScalar')->willReturnCallback(static fn ($path) => $settings[$path] ?? null);

        $requests = new RequestStack();
        $requests->push(Request::create('https://example.test/realisations'));

        $hours = (new OpeningHours(null, null, 'Europe/Paris', [1 => [['09:00', '12:00'], ['13:00', '17:00']]]))
            ->withSpecialDays([new SpecialDay(new \DateTimeImmutable('2026-12-25'))]);

        return new LocalBusiness($hours, $bag, $requests, $config);
    }

    public function testTheSettingsTheConfigurationAndTheHours(): void
    {
        $schema = $this->business(['base.settings.title' => 'Atelier Exemple', 'base.settings.slogan' => 'Les experts en signalétique', 'base.settings.phone' => '03 00 00 00 01'])->schema();

        $this->assertSame('https://schema.org', $schema['@context']);
        $this->assertSame('LocalBusiness', $schema['@type']);
        $this->assertSame('https://example.test/#business', $schema['@id']);
        $this->assertSame('Atelier Exemple', $schema['name']);
        $this->assertSame('Les experts en signalétique', $schema['slogan']);
        $this->assertSame('https://example.test/', $schema['url']);
        $this->assertSame('03 00 00 00 01', $schema['telephone'], 'the back office wins over the configuration');
        $this->assertSame('https://example.test/assets/ico/logo.png', $schema['image']);
        $this->assertSame(['@type' => 'PostalAddress', 'streetAddress' => '1 rue des Exemples', 'postalCode' => '67000', 'addressLocality' => 'Strasbourg', 'addressCountry' => 'FR'], $schema['address']);
        $this->assertSame(['@type' => 'GeoCoordinates', 'latitude' => 48.6, 'longitude' => 7.75], $schema['geo']);
        $this->assertSame(['https://www.facebook.com/atelierexemple/'], $schema['sameAs']);
        $this->assertSame('Exemple Groupe', $schema['parentOrganization']['name']);

        $this->assertSame(['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'Monday', 'opens' => '09:00', 'closes' => '12:00'], $schema['openingHoursSpecification'][0]);
        $this->assertSame('2026-12-25', $schema['specialOpeningHoursSpecification'][0]['validFrom']);
    }

    public function testWhatNobodyGivesIsLeftOutAndTheCallerWins(): void
    {
        $bare = ['type' => 'Restaurant'] + array_fill_keys(['name', 'url', 'telephone', 'email', 'image', 'price_range'], null)
            + ['address' => [], 'geo' => [], 'same_as' => [], 'area_served' => [], 'extra' => []];
        $schema = $this->business([], $bare)->schema(['servesCuisine' => 'Japanese', 'url' => null]);

        $this->assertSame('Restaurant', $schema['@type']);
        $this->assertSame('Japanese', $schema['servesCuisine']);
        foreach (['name', 'telephone', 'email', 'image', 'address', 'geo', 'sameAs', 'areaServed', 'priceRange', 'url'] as $absent) {
            $this->assertArrayNotHasKey($absent, $schema);
        }
    }

    public function testTheScriptCannotBeBrokenOutOf(): void
    {
        $html = $this->business(['base.settings.title' => 'A </script><b> & Co'])->jsonLd();

        $this->assertStringStartsWith('<script type="application/ld+json">{', $html);
        $this->assertSame(1, substr_count($html, '</script>'));
        $this->assertSame('A </script><b> & Co', json_decode(substr($html, 35, -9), true)['name']);
        $this->assertStringContainsString('"https://schema.org"', $html, 'addresses are readable');
    }
}
