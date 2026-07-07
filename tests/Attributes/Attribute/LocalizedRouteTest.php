<?php

namespace Tests\Base\Attributes\Attribute;

use Base\Attributes\Attribute\LocalizedRoute;
use Base\Service\BaseService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Yaml\Yaml;

/**
 * LocalizedRoute shares its translationKey resolution with Route (see
 * RouteTest) via RoutePathDictionaryTrait — these tests focus on what's
 * different: no host/domain/subdomain templating, so it's a safe drop-in
 * for Symfony's own #[Route] on any controller.
 */
class LocalizedRouteTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/base-bundle-localized-route-test-' . uniqid();
        mkdir($this->projectDir . '/config/routes', 0777, true);

        (new ReflectionClass(BaseService::class))->setStaticPropertyValue('projectDir', $this->projectDir);
        (new ReflectionClass(LocalizedRoute::class))->setStaticPropertyValue('translations', null);
    }

    protected function tearDown(): void
    {
        (new ReflectionClass(BaseService::class))->setStaticPropertyValue('projectDir', null);
        (new ReflectionClass(LocalizedRoute::class))->setStaticPropertyValue('translations', null);

        array_map('unlink', glob($this->projectDir . '/config/routes/*'));
        @rmdir($this->projectDir . '/config/routes');
        @rmdir($this->projectDir . '/config');
        @rmdir($this->projectDir);
    }

    public function testTranslationKeyResolvesToThePerLanguagePathArray(): void
    {
        file_put_contents(
            $this->projectDir . LocalizedRoute::TRANSLATIONS_FILE,
            Yaml::dump(['calendar' => ['en' => '/calendar', 'fr' => '/calendrier']])
        );

        $route = new LocalizedRoute(translationKey: 'calendar', name: 'app_calendar');

        $this->assertSame(['en' => '/calendar', 'fr' => '/calendrier'], $route->path);
    }

    public function testMissingKeyThrows(): void
    {
        file_put_contents($this->projectDir . LocalizedRoute::TRANSLATIONS_FILE, Yaml::dump(['other' => ['en' => '/other']]));

        $this->expectException(\LogicException::class);

        new LocalizedRoute(translationKey: 'calendar', name: 'app_calendar');
    }

    public function testDoesNotComposeAnyHostTemplating(): void
    {
        $route = new LocalizedRoute(path: '/plain', name: 'app_plain');

        $this->assertNull($route->host, 'LocalizedRoute must not force host/domain/subdomain matching the way Route does');
    }

    public function testPlainPathStillWorksWithoutATranslationKey(): void
    {
        $route = new LocalizedRoute(path: '/plain', name: 'app_plain');

        $this->assertSame('/plain', $route->path);
    }
}
