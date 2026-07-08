<?php

namespace Tests\Base\Service\Model;

use Base\Attributes\AttributeReader;
use Base\Routing\AdvancedRouterInterface;
use Base\Service\Model\Breadcrumb;
use Base\Service\TranslatorInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext;
use Tests\Base\Fixtures\Breadcrumb\FixtureController;

class BreadcrumbTest extends TestCase
{
    private AdvancedRouterInterface $router;
    private TranslatorInterface $translator;

    protected function setUp(): void
    {
        $this->router = $this->createMock(AdvancedRouterInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);

        // Breadcrumb's constructor reaches AttributeReader purely through
        // SingletonTrait::getInstance() — same mechanism, same production
        // incident class documented in SingletonTraitTest. AttributeReader's
        // real constructor needs a dozen container services; a bare test
        // process never seeds the singleton the way Symfony's container
        // normally would, so getInstance()'s "new self()" fallback would
        // hit an ArgumentCountError (silently caught, returns null) and
        // compute() would fatal on a null->getReflClass() call. Seed a mock
        // directly — compute() only ever calls getReflClass(), which on the
        // real class is just `new ReflectionClass($class)` with no
        // dependency on any of those container services anyway.
        $attributeReader = $this->createMock(AttributeReader::class);
        $attributeReader->method('getReflClass')->willReturnCallback(
            fn($classNameOrMetadataOrRefl) => new ReflectionClass($classNameOrMetadataOrRefl)
        );
        (new ReflectionClass(AttributeReader::class))->setStaticPropertyValue('_instance', $attributeReader);
    }

    protected function tearDown(): void
    {
        (new ReflectionClass(AttributeReader::class))->setStaticPropertyValue('_instance', null);
    }

    private function breadcrumb(array $options = [], ?string $template = null): Breadcrumb
    {
        return new Breadcrumb($this->router, $this->translator, $options, $template);
    }

    public function testAppendAddsToTheEndAndPrependAddsToTheStart(): void
    {
        $breadcrumb = $this->breadcrumb();
        $breadcrumb->appendItem('A');
        $breadcrumb->appendItem('B');
        $breadcrumb->prependItem('C');

        $this->assertSame(['C', 'A', 'B'], array_column($breadcrumb->getItems(), 'label'));
    }

    public function testFirstLastAndIndexedAccessors(): void
    {
        $breadcrumb = $this->breadcrumb();
        $breadcrumb->appendItem('A');
        $breadcrumb->appendItem('B');

        $this->assertSame('A', $breadcrumb->getFirstItem()['label']);
        $this->assertSame('B', $breadcrumb->getLastItem()['label']);
        $this->assertSame('A', $breadcrumb->getItem(0)['label']);
        $this->assertNull($breadcrumb->getItem(99));
    }

    public function testItemsWithARouteAreResolvedToAUrl(): void
    {
        $this->router->method('generate')
            ->with('app_home', ['id' => 42])
            ->willReturn('/home/42');

        $breadcrumb = $this->breadcrumb();
        $breadcrumb->appendItem('Home', 'app_home', ['id' => 42]);

        $item = $breadcrumb->getFirstItem();
        $this->assertSame('/home/42', $item['url']);
        $this->assertSame('app_home', $item['route']);
    }

    public function testItemsWithoutARouteHaveNoUrl(): void
    {
        $breadcrumb = $this->breadcrumb();
        $breadcrumb->appendItem('Plain label');

        $this->assertNull($breadcrumb->getFirstItem()['url']);
    }

    public function testClearEmptiesTheItemList(): void
    {
        $breadcrumb = $this->breadcrumb();
        $breadcrumb->appendItem('A');
        $breadcrumb->clear();

        $this->assertSame(0, $breadcrumb->getLength());
        $this->assertSame([], $breadcrumb->getItems());
    }

    public function testRemoveItemDropsTheOnlyEntry(): void
    {
        $breadcrumb = $this->breadcrumb();
        $breadcrumb->appendItem('A');
        $breadcrumb->removeItem(0);

        $this->assertSame(0, $breadcrumb->getLength());
    }

    public function testArrayAccessPushSyntaxAppendsAnItem(): void
    {
        $breadcrumb = $this->breadcrumb();
        $breadcrumb[] = ['Home'];

        $this->assertTrue(isset($breadcrumb[0]));
        $this->assertSame('Home', $breadcrumb[0]['label']);

        unset($breadcrumb[0]);
        $this->assertFalse(isset($breadcrumb[0]));
    }

    public function testIteratesInInsertionOrderAndIsCountable(): void
    {
        $breadcrumb = $this->breadcrumb();
        $breadcrumb->appendItem('A');
        $breadcrumb->appendItem('B');
        $breadcrumb->appendItem('C');

        $this->assertCount(3, $breadcrumb);

        $labels = [];
        foreach ($breadcrumb as $item) {
            $labels[] = $item['label'];
        }
        $this->assertSame(['A', 'B', 'C'], $labels);
    }

    public function testOptionsAreStoredAndRemovable(): void
    {
        $breadcrumb = $this->breadcrumb();
        $breadcrumb->addOption('offset', 1);
        $breadcrumb->addOptions(['icons' => true, 'offset' => 2]);

        $this->assertSame(['offset' => 2, 'icons' => true], $breadcrumb->getOptions());
        $this->assertSame(2, $breadcrumb->getOption('offset'));

        $breadcrumb->removeOption('offset');
        $this->assertNull($breadcrumb->getOption('offset'));

        $breadcrumb->setOptions(['page_title' => 'Foo']);
        $this->assertSame(['page_title' => 'Foo'], $breadcrumb->getOptions());
    }

    public function testConstructorOptionsAndTemplateAreApplied(): void
    {
        $breadcrumb = $this->breadcrumb(['offset' => 1], '@Custom/breadcrumb.html.twig');

        $this->assertSame(1, $breadcrumb->getOption('offset'));
        $this->assertSame('@Custom/breadcrumb.html.twig', $breadcrumb->getTemplate());
    }

    /**
     * @dataProvider routeParameterCases
     */
    public function testGetRouteParameters(?string $url, ?string $pattern, ?array $expected): void
    {
        $breadcrumb = $this->breadcrumb();
        $this->assertSame($expected, $breadcrumb->getRouteParameters($url, $pattern));
    }

    public static function routeParameterCases(): array
    {
        return [
            'no pattern returns null' => ['/foo/bar', null, null],
            'exact static match, no params' => ['/foo/bar', '/foo/bar', []],
            'single placeholder is captured' => ['/foo/42', '/foo/{id}', ['id' => '42']],
            'multiple placeholders are captured' => ['/foo/42/bar/slug-x', '/foo/{id}/bar/{slug}', ['id' => '42', 'slug' => 'slug-x']],
            'static segment mismatch returns null' => ['/foo/baz', '/foo/bar', null],
            'url longer than pattern returns null' => ['/foo/bar/baz', '/foo/bar', null],
        ];
    }

    public function testGetRouteNameReturnsEmptyStringForNullUrl(): void
    {
        $breadcrumb = $this->breadcrumb();
        $this->assertSame('', $breadcrumb->getRouteName(null));
    }

    public function testGetRouteNameResolvesViaTheRouter(): void
    {
        $this->router->method('getContext')->willReturn(new RequestContext());
        $this->router->method('match')->with('/foo')->willReturn(['_route' => 'app_foo']);

        $breadcrumb = $this->breadcrumb();
        $this->assertSame('app_foo', $breadcrumb->getRouteName('/foo'));
    }

    public function testGetRouteNameReturnsEmptyStringWhenTheRouteIsNotFound(): void
    {
        $this->router->method('getContext')->willReturn(new RequestContext());
        $this->router->method('match')->willThrowException(new ResourceNotFoundException());

        $breadcrumb = $this->breadcrumb();
        $this->assertSame('', $breadcrumb->getRouteName('/unknown'));
    }

    public function testGetControllerResolvesViaTheRouter(): void
    {
        $this->router->method('getContext')->willReturn(new RequestContext());
        $this->router->method('match')->with('/foo')->willReturn(['_controller' => 'App\\Controller\\FooController::index']);

        $breadcrumb = $this->breadcrumb();
        $this->assertSame('App\\Controller\\FooController::index', $breadcrumb->getController('/foo'));
    }

    public function testGetControllerReturnsEmptyStringForAnEmptyPath(): void
    {
        $this->router->method('getContext')->willReturn(new RequestContext());
        $this->router->expects($this->never())->method('match');

        $breadcrumb = $this->breadcrumb();
        $this->assertSame('', $breadcrumb->getController(''));
    }

    public function testComputeIsANoOpWithoutARequest(): void
    {
        $breadcrumb = $this->breadcrumb();
        $result = $breadcrumb->compute();

        $this->assertSame($breadcrumb, $result);
        $this->assertSame([], $breadcrumb->getItems());
    }

    public function testComputeBuildsAnItemFromTheMatchedControllersAttributes(): void
    {
        $this->router->method('getContext')->willReturn(new RequestContext());
        $this->router->method('match')->willReturnCallback(function (string $path) {
            return match ($path) {
                '/foo' => ['_route' => 'app_foo', '_controller' => FixtureController::class . '::fooAction'],
                default => throw new ResourceNotFoundException(),
            };
        });
        $this->router->method('generate')->with('app_foo', [])->willReturn('/foo');

        $this->translator->method('trans')->with('@controllers.app_foo.title', [])->willReturn('Foo Page');

        $breadcrumb = $this->breadcrumb();
        $breadcrumb->compute(Request::create('/foo'));

        $this->assertSame(['Foo Page'], array_column($breadcrumb->getItems(), 'label'));
        $this->assertSame(['app_foo'], array_column($breadcrumb->getItems(), 'route'));
        $this->assertSame(['/foo'], array_column($breadcrumb->getItems(), 'url'));
        $this->assertSame(['fa-solid fa-house'], array_values($breadcrumb->getOption('icons')));
    }

    /**
     * The path is walked from the full request path up through each parent
     * directory (dirname()) until it runs out — a route match at a deeper
     * level (here /foo/42, a placeholder route) is prepended AFTER the one
     * found by walking up to its parent (/foo), giving root-to-leaf order.
     */
    public function testComputeWalksUpTheRequestPathBuildingParentToChildOrder(): void
    {
        $this->router->method('getContext')->willReturn(new RequestContext());
        $this->router->method('match')->willReturnCallback(function (string $path) {
            return match ($path) {
                '/foo/42' => ['_route' => 'app_foo_show', '_controller' => FixtureController::class . '::showAction'],
                '/foo' => ['_route' => 'app_foo', '_controller' => FixtureController::class . '::fooAction'],
                default => throw new ResourceNotFoundException(),
            };
        });
        $this->router->method('generate')->willReturnCallback(fn(string $route) => "/generated/$route");

        $this->translator->method('trans')->willReturnCallback(fn(string $id) => match ($id) {
            '@controllers.app_foo_show.id.title' => 'Item #42',
            '@controllers.app_foo.title' => 'Foo Page',
            default => $id,
        });

        $breadcrumb = $this->breadcrumb();
        $breadcrumb->compute(Request::create('/foo/42'));

        $this->assertSame(['Foo Page', 'Item #42'], array_column($breadcrumb->getItems(), 'label'));
        $this->assertSame(['app_foo', 'app_foo_show'], array_column($breadcrumb->getItems(), 'route'));
    }

    /**
     * A second compute() call short-circuits on the $computed flag instead
     * of re-walking the path — the router is only ever consulted during the
     * first call (2 match() calls: getController() and getRouteName() each
     * match the one real path level, "/foo"; the final "" level returns
     * early without matching at all, per testGetControllerReturnsEmptyStringForAnEmptyPath).
     */
    public function testComputeIsMemoizedAndOnlyRunsOnce(): void
    {
        $this->router->method('getContext')->willReturn(new RequestContext());
        $this->router->expects($this->exactly(2))->method('match')->willReturnCallback(function (string $path) {
            return match ($path) {
                '/foo' => ['_route' => 'app_foo', '_controller' => FixtureController::class . '::fooAction'],
                default => throw new ResourceNotFoundException(),
            };
        });
        $this->router->method('generate')->willReturn('/foo');
        $this->translator->method('trans')->willReturn('Foo Page');

        $breadcrumb = $this->breadcrumb();
        $breadcrumb->compute(Request::create('/foo'));
        $breadcrumb->compute(Request::create('/foo'));

        $this->assertCount(1, $breadcrumb->getItems());
    }
}
