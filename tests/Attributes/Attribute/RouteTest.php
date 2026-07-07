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
}
