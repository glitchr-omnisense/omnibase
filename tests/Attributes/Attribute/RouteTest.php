<?php

namespace Tests\Base\Attributes\Attribute;

use Base\Attributes\Attribute\Route;
use Base\Service\BaseService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Yaml\Yaml;

/**
 * translationKey resolution against the central route path dictionary
 * (config/routes/paths.yaml, relative to BaseService::getProjectDir()).
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
        mkdir($this->projectDir . '/config/routes', 0777, true);

        (new ReflectionClass(BaseService::class))->setStaticPropertyValue('projectDir', $this->projectDir);
        (new ReflectionClass(Route::class))->setStaticPropertyValue('translations', null);
    }

    protected function tearDown(): void
    {
        (new ReflectionClass(BaseService::class))->setStaticPropertyValue('projectDir', null);
        (new ReflectionClass(Route::class))->setStaticPropertyValue('translations', null);

        array_map('unlink', glob($this->projectDir . '/config/routes/*'));
        @rmdir($this->projectDir . '/config/routes');
        @rmdir($this->projectDir . '/config');
        @rmdir($this->projectDir);
    }

    private function writeDictionary(array $dictionary): void
    {
        file_put_contents($this->projectDir . Route::TRANSLATIONS_FILE, Yaml::dump($dictionary));
    }

    public function testTranslationKeyResolvesToThePerLanguagePathArray(): void
    {
        $this->writeDictionary([
            'calendar' => ['en' => '/calendar', 'fr' => '/calendrier'],
        ]);

        $route = new Route(translationKey: 'calendar', name: 'app_calendar');

        $this->assertSame(['en' => '/calendar', 'fr' => '/calendrier'], $route->path);
    }

    public function testMissingKeyThrows(): void
    {
        $this->writeDictionary(['other' => ['en' => '/other']]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/"calendar"/');

        new Route(translationKey: 'calendar', name: 'app_calendar');
    }

    public function testMissingDictionaryFileThrows(): void
    {
        // No file written at all this time.
        $this->expectException(\LogicException::class);

        new Route(translationKey: 'calendar', name: 'app_calendar');
    }

    /**
     * The dictionary is parsed once and memoized — a second Route built
     * after the file changes on disk must still see the first parse.
     */
    public function testDictionaryIsParsedOnceAndCached(): void
    {
        $this->writeDictionary(['calendar' => ['en' => '/calendar']]);
        new Route(translationKey: 'calendar', name: 'a');

        $this->writeDictionary(['calendar' => ['en' => '/changed']]);
        $route = new Route(translationKey: 'calendar', name: 'b');

        $this->assertSame(['en' => '/calendar'], $route->path);
    }

    public function testPlainPathStillWorksWithoutATranslationKey(): void
    {
        $route = new Route(path: '/plain', name: 'app_plain');

        $this->assertSame('/plain', $route->path);
    }

    public function testArrayPathStillWorksWithoutATranslationKey(): void
    {
        $route = new Route(path: ['en' => '/calendar', 'fr' => '/calendrier'], name: 'app_calendar');

        $this->assertSame(['en' => '/calendar', 'fr' => '/calendrier'], $route->path);
    }
}
