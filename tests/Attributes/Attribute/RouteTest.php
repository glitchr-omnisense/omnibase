<?php

namespace Tests\Base\Attributes\Attribute;

use Base\Attributes\Attribute\Route;
use Base\Service\BaseService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Yaml\Yaml;

/**
 * Dictionary-key resolution against the central route path dictionary
 * (config/routes_paths.yaml, relative to BaseService::getProjectDir()): a
 * string $path that doesn't start with "/" is treated as a key rather than
 * a literal path, since real route paths always start with "/".
 * BaseService::$projectDir and Route's own memoized dictionary cache are
 * both static, process-wide state — reset in tearDown() so tests stay
 * independent of run order, same convention as SingletonTraitTest.
 */
class RouteTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/base-bundle-route-test-' . uniqid();
        mkdir($this->projectDir . '/config', 0777, true);

        (new ReflectionClass(BaseService::class))->setStaticPropertyValue('projectDir', $this->projectDir);
        (new ReflectionClass(Route::class))->setStaticPropertyValue('translations', null);
    }

    protected function tearDown(): void
    {
        (new ReflectionClass(BaseService::class))->setStaticPropertyValue('projectDir', null);
        (new ReflectionClass(Route::class))->setStaticPropertyValue('translations', null);

        @unlink($this->projectDir . Route::TRANSLATIONS_FILE);
        @rmdir($this->projectDir . '/config');
        @rmdir($this->projectDir);
    }

    private function writeDictionary(array $dictionary): void
    {
        file_put_contents($this->projectDir . Route::TRANSLATIONS_FILE, Yaml::dump($dictionary));
    }

    public function testBareKeyResolvesToThePerLanguagePathArray(): void
    {
        $this->writeDictionary([
            'calendar' => ['en' => '/calendar', 'fr' => '/calendrier'],
        ]);

        $route = new Route(path: 'calendar', name: 'app_calendar');

        $this->assertSame(['en' => '/calendar', 'fr' => '/calendrier'], $route->path);
    }

    public function testMissingKeyThrows(): void
    {
        $this->writeDictionary(['other' => ['en' => '/other']]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/"calendar"/');

        new Route(path: 'calendar', name: 'app_calendar');
    }

    public function testMissingDictionaryFileThrows(): void
    {
        // No file written at all this time.
        $this->expectException(\LogicException::class);

        new Route(path: 'calendar', name: 'app_calendar');
    }

    /**
     * The dictionary is parsed once and memoized — a second Route built
     * after the file changes on disk must still see the first parse.
     */
    public function testDictionaryIsParsedOnceAndCached(): void
    {
        $this->writeDictionary(['calendar' => ['en' => '/calendar']]);
        new Route(path: 'calendar', name: 'a');

        $this->writeDictionary(['calendar' => ['en' => '/changed']]);
        $route = new Route(path: 'calendar', name: 'b');

        $this->assertSame(['en' => '/calendar'], $route->path);
    }

    public function testLiteralPathStartingWithSlashIsNeverTreatedAsAKey(): void
    {
        // No dictionary file exists at all — if this were misread as a key
        // lookup, it would throw.
        $route = new Route(path: '/plain', name: 'app_plain');

        $this->assertSame('/plain', $route->path);
    }

    public function testArrayPathStillWorksAsALiteral(): void
    {
        $route = new Route(path: ['en' => '/calendar', 'fr' => '/calendrier'], name: 'app_calendar');

        $this->assertSame(['en' => '/calendar', 'fr' => '/calendrier'], $route->path);
    }

    public function testNullPathIsNeverTreatedAsAKey(): void
    {
        // A null $path (e.g. a class-level attribute used only for a
        // prefix/options) must not trigger dictionary resolution.
        $route = new Route(name: 'app_prefix_only');

        $this->assertNull($route->path);
    }

    /**
     * Regression: the constructor used to unconditionally manufacture a
     * fully sentinel-templated host for every route, even when the caller
     * never asked for host/domain/subdomain/machine/port at all. Since
     * nothing ever sends a Host header that literally matches those
     * tokens, every plain route built this way was silently unroutable —
     * this was only ever caught because this bundle's Route attribute had
     * exactly one real caller in the host app (CalendarController), and it
     * never passed any host-related argument. Host templating is opt-in:
     * no host args in, no host constraint out — same as Symfony's own
     * Route attribute.
     */
    public function testNoHostArgumentsLeavesTheRouteHostUnconstrained(): void
    {
        $route = new Route(path: '/plain', name: 'app_plain');

        $this->assertNull($route->host);
    }

    public function testPortDefaultsToTheSentinelTokenWhenOtherHostArgumentsAreProvided(): void
    {
        $route = new Route(path: '/plain', name: 'app_plain', domain: 'example.org');

        $this->assertStringContainsString('\{_port\}', $route->host);
    }

    public function testPortAloneIsEnoughToOptIntoHostTemplating(): void
    {
        $route = new Route(path: '/plain', name: 'app_plain', port: 8443);

        $this->assertStringContainsString(':8443', $route->host);
        $this->assertStringNotContainsString('\{_port\}', $route->host);
    }

    /**
     * Regression: the host used to be built via compose_url(), which always
     * prepends a scheme once a domain is set ("https://..."). A real Host
     * request header never carries a scheme, so that host requirement could
     * never match a real request — every host-templated route silently fell
     * through to whatever route came next in the collection.
     */
    public function testHostNeverCarriesAScheme(): void
    {
        $route = new Route(path: '/plain', name: 'app_plain', port: 8443);

        $this->assertStringNotContainsString('://', $route->host);
        $this->assertSame('\{_machine\}.\{_subdomain\}.\{_domain\}:8443', $route->host);
    }

    public function testHostWithExplicitDomainNeverCarriesAScheme(): void
    {
        $route = new Route(path: '/plain', name: 'app_plain', domain: 'example.org', subdomain: 'm');

        $this->assertSame('\{_machine\}.m.example.org:\{_port\}', $route->host);
    }
}
