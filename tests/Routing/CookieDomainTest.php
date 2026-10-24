<?php

namespace Tests\Base\Routing;

use Base\Routing\CookieDomain;
use PHPUnit\Framework\TestCase;

/**
 * The domain the site's cookies are written for (step R29: the browser's
 * PHPUSERID, AdvancedRouter::getDomain() under Symfony and omnibridge/laravel's
 * sign-in effects under Laravel).
 */
class CookieDomainTest extends TestCase
{
    public function testTheRequestsDomainWhenConfiguredElseTheFirstConfigured(): void
    {
        $this->assertSame('apfelschorlette.fr', CookieDomain::of('https://m.apfelschorlette.fr/japon', ['apfelschorlette.fr', 'localhost']), 'its subdomains share it');
        $this->assertSame('localhost', CookieDomain::of('http://localhost:8000/', ['apfelschorlette.fr', 'localhost']));
        // "lesringostars.symfony.test" is read as the domain symfony.test of the subdomain lesringostars.
        $this->assertSame('lesringostars.symfony.test', CookieDomain::of('https://lesringostars.symfony.test:8663/', ['lesringostars.symfony.test', 'localhost']));
        $this->assertSame('localhost', CookieDomain::of('https://example.org/', []), 'no fallbacks: localhost, as the router answers');
    }

    public function testTheConfiguredDomainsOfALanguage(): void
    {
        $fallbacks = [
            ['locale' => 'de', 'domain' => ['beispiel.de']],
            ['locale' => null, 'domain' => ['example.org', 'localhost'], 'subdomain' => ['m']],
        ];

        $this->assertSame(['beispiel.de'], CookieDomain::domains(true, $fallbacks, ['de-DE', 'de']));
        $this->assertSame(['example.org', 'localhost'], CookieDomain::domains(true, $fallbacks, ['fr-FR', 'fr']));
        $this->assertSame(['example.org'], CookieDomain::domains(true, [['domain' => 'example.org']], ['fr']), 'an entry without locale; a domain alone');
        $this->assertSame([], CookieDomain::domains(false, $fallbacks, ['fr']));
    }
}
