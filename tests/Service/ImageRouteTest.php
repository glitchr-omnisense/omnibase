<?php

namespace Tests\Base\Service;

use Base\Controller\UX\MediaController;
use Base\Service\ImageRoute;
use Base\Service\MediaService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * omnibase's image route, its answers moved out of MediaController into
 * Base\Service\ImageRoute for any host (audit "lesringostars face au master
 * plan", R6), under Symfony:
 * - ImageRoute::ROUTES, which a host without the framework declares, is
 *   MediaController's routes - names, paths, the data's requirement, the
 *   order Symfony matches them in, each its action - ux_debug_* aside;
 * - MediaController still answers its routes, through ImageRoute: a
 *   thumbnail's address without its extension is sent to the one with it
 *   (301), which answers the thumbnail's file; ImageRoute itself answers the
 *   same bytes.
 */
final class ImageRouteTest extends KernelTestCase
{
    /** MediaController's actions, by ImageRoute's: [action, cacheless]. */
    private const ACTIONS = [
        'Serve' => ['contents', false],
        'ImageCropCacheless' => ['imageCrop', true],
        'ImageCrop' => ['imageCrop', false],
        'ImageWebpCacheless' => ['imageWebp', true],
        'ImageCacheless' => ['image', true],
        'ImageWebp' => ['imageWebp', false],
        'ImageSvg' => ['imageSvg', false],
        'Image' => ['image', false],
    ];

    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }
        if (!\extension_loaded('imagick')) {
            self::markTestSkipped('imagick is not loaded');
        }

        self::bootKernel();
    }

    public function testTheRoutesAreMediaControllers(): void
    {
        $routes = [];
        foreach (self::getContainer()->get('router')->getRouteCollection()->all() as $name => $route) {
            $controller = $route->getDefault('_controller');
            if (!\is_string($controller) || !str_starts_with($controller, MediaController::class.'::') || str_starts_with($name, 'ux_debug_')) {
                continue;
            }
            self::assertSame('.+', $route->getRequirement('data'), $name);
            [$action, $cacheless] = self::ACTIONS[substr($controller, \strlen(MediaController::class.'::'))];
            $routes[$name] = [$route->getPath(), $action, $cacheless];
        }

        self::assertSame(ImageRoute::ROUTES, $routes);
        foreach (ImageRoute::ROUTES as [, $action]) {
            self::assertTrue(method_exists(ImageRoute::class, $action), $action);
        }
    }

    public function testTheControllerAnswersThroughTheImageRoute(): void
    {
        $source = sys_get_temp_dir().'/omnibase-image-route-'.getmypid().'.png';
        $picture = new \Imagick();
        $picture->newImage(400, 200, 'steelblue', 'png');
        $picture->writeImage($source);

        try {
            /** @var MediaService $media */
            $media = self::getContainer()->get(MediaService::class);
            $thumbnail = (string) $media->thumbnail($source, 100, 100, ['webp' => false]);
            self::assertMatchesRegularExpression('#^/images/.+/image\.png$#', $thumbnail);
            $bare = substr($thumbnail, 0, -\strlen('/image.png'));

            $redirection = self::$kernel->handle(Request::create($bare));
            self::assertSame(301, $redirection->getStatusCode());
            self::assertSame($thumbnail, $redirection->headers->get('Location'));

            $response = self::$kernel->handle(Request::create($thumbnail));
            self::assertSame(200, $response->getStatusCode());
            self::assertSame('image/png', $response->headers->get('Content-Type'));
            $size = getimagesizefromstring((string) $response->getContent());
            self::assertSame([100, 50], [$size[0], $size[1]]);

            $match = $media->getRouter()->getRouteMatch($thumbnail);
            $direct = (new ImageRoute($media, self::getContainer()->get(\Base\Service\Flysystem::class)))->image($match['data'], 'png');
            self::assertSame($response->getContent(), $direct->getContent());
        } finally {
            @unlink($source);
        }
    }
}
