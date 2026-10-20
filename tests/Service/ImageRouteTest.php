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
 *   same bytes;
 * - a bitmap's address names its own type and its bytes are of the type the
 *   address names: an uploaded JPEG stored without extension (an avatar) is
 *   image.jpg and answers a JPEG - it was image.png, holding a JPEG, which a
 *   web server serving the cache by extension sent as image/png -, and a
 *   bitmap asked as png is written as a PNG.
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

    public function testAStoredJpegWithoutExtensionIsAddressedAndAnsweredAsAJpeg(): void
    {
        $publicDir = rtrim((string) \Base\Service\Flysystem::getPublicDir(), '/');
        $name = '/omnibase-image-route-'.getmypid();
        $picture = new \Imagick();
        $picture->newImage(400, 200, 'steelblue', 'jpeg');
        $picture->writeImage('jpeg:'.$publicDir.$name);

        try {
            /** @var MediaService $media */
            $media = self::getContainer()->get(MediaService::class);
            self::assertSame('jpg', $media->getExtension($name), 'the type of a public path, read from its bytes');

            $thumbnail = (string) $media->thumbnail($name, 100, 100, ['webp' => false]);
            self::assertMatchesRegularExpression('#^/images/.+/image\.jpg$#', $thumbnail);
            self::assertSame('jpg', $media->getExtension($thumbnail), 'an address of the route: the type of its source');
            self::assertSame('image/jpeg', $media->getMimeType($name), 'the type a template prints (|mimetype: an audio or video <source>)');
            self::assertSame('image/jpeg', $media->getMimeType($thumbnail));
            self::assertMatchesRegularExpression('#^/images/.+/image\.jpg$#', (string) $media->image($thumbnail, ['webp' => false]), 'its address given again (imagify)');

            $png = substr($thumbnail, 0, -\strlen('jpg')).'png';
            $redirection = self::$kernel->handle(Request::create($png));
            self::assertSame(301, $redirection->getStatusCode(), 'a JPEG asked as png goes to its own address');
            self::assertSame($thumbnail, $redirection->headers->get('Location'));

            $response = self::$kernel->handle(Request::create($thumbnail));
            self::assertSame(200, $response->getStatusCode());
            self::assertSame('image/jpeg', $response->headers->get('Content-Type'));
            self::assertSame('image/jpeg', (new \finfo(\FILEINFO_MIME_TYPE))->buffer((string) $response->getContent()));
        } finally {
            @unlink($publicDir.$name);
        }
    }

    /**
     * A sound kept in public/ (an article's audio: Uploader's public path, made an address of the
     * image route by MediaService::audio(), soundify's): its address answers the file, of its type.
     * The route read the resolved path as a file of the project or of a storage only, and every
     * audio player of the sites asked a 404.
     */
    public function testAPublicFileIsServedByItsContentsAddress(): void
    {
        $publicDir = rtrim((string) \Base\Service\Flysystem::getPublicDir(), '/');
        $name = '/omnibase-image-route-sound-'.getmypid();
        $bytes = "ID3\x03\x00\x00\x00\x00\x00\x00".str_repeat("\xff\xfb\x90\x00", 64);
        file_put_contents($publicDir.$name, $bytes);

        try {
            /** @var MediaService $media */
            $media = self::getContainer()->get(MediaService::class);
            $address = (string) $media->audio($name);
            self::assertMatchesRegularExpression('#^/contents/#', $address);
            self::assertSame($name, $media->resolve(substr($address, \strlen('/contents/')))['path'] ?? null, 'the address keeps the public path');

            $response = self::$kernel->handle(Request::create($address));
            self::assertSame(200, $response->getStatusCode());
            self::assertSame($bytes, $response->getContent());
            self::assertSame($media->getMimeType($name), $response->headers->get('Content-Type'), 'served as the player says it is');
        } finally {
            @unlink($publicDir.$name);
        }
    }

    public function testABitmapIsWrittenInTheFormatItsAddressNames(): void
    {
        $source = sys_get_temp_dir().'/omnibase-image-route-'.getmypid().'.jpg';
        $picture = new \Imagick();
        $picture->newImage(400, 200, 'steelblue', 'jpeg');
        $picture->writeImage($source);

        try {
            $format = new \ReflectionMethod(ImageRoute::class, 'format');
            self::assertSame(['quality' => 0.8, 'format' => 'png'], $format->invoke(null, ['quality' => 0.8], 'png'));
            self::assertSame('jpeg', $format->invoke(null, [], 'JPE')['format']);

            /** @var MediaService $media */
            $media = self::getContainer()->get(MediaService::class);
            foreach (['png' => 'image/png', 'jpg' => 'image/jpeg', 'gif' => 'image/gif'] as $extension => $type) {
                $file = $media->filter($source, ['local_cache' => false], new \Base\Imagine\Filter\Format\BitmapFilter(null, $format->invoke(null, [], $extension), [new \Base\Imagine\Filter\Basic\ThumbnailFilter(100, 100)]));
                self::assertSame($type, mime_content_type((string) $file), "a JPEG asked as $extension");
                @unlink((string) $file);
            }
        } finally {
            @unlink($source);
        }
    }
}
