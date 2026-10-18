<?php

namespace Tests\Base\Contract;

use Base\Bridge\Symfony\ServiceMedia;
use Base\Contract\Link;
use Base\Contract\Runtime;
use Base\Entity\Layout\Image;
use Base\Entity\Layout\ImageCrop;
use Base\Imagine\Filter\Basic\ThumbnailFilter;
use Base\Service\BaseService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The image route's addresses, moved off BaseTrait's MediaService and router
 * onto the Media and Urls contracts (plan "omnibase agnostique", C4b), under
 * Symfony: Image::__toLink() and ImageCrop::__toLink() give, for a dozen
 * requests (a thumbnail, a crop, the extension, webp or not, filters, an
 * absolute URL), the strings their old code gave - copied here as it was,
 * over BaseService's MediaService and router. tests/BareTest's
 * testTheBaseTraitSeamsAnswerWithoutTheFramework builds the same links where
 * there is no framework.
 */
final class MediaTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }

        self::bootKernel();
    }

    public function testTheRegistryHoldsMediaService(): void
    {
        self::assertInstanceOf(ServiceMedia::class, Runtime::media());
        $media = BaseService::getMediaService();
        foreach (['/images/user.jpg', '/images/user.png', 'photo.webp', null] as $path) {
            self::assertSame($media->getExtension($path), Runtime::media()->extension($path), (string) $path);
            self::assertSame($media->obfuscate($path, ['identifier' => '100x100']), Runtime::media()->obfuscate($path, ['identifier' => '100x100']), (string) $path);
        }
    }

    /** @return iterable<string, array{array<string, mixed>, int}> */
    public static function requests(): iterable
    {
        yield 'as it is' => [[], Link::ABSOLUTE_PATH];
        yield 'a thumbnail' => [['thumbnail' => [120, 80]], Link::ABSOLUTE_PATH];
        yield 'a thumbnail, whole' => [['thumbnail' => [120, 80]], Link::ABSOLUTE_URL];
        yield 'a crop' => [['crop' => 'square'], Link::ABSOLUTE_PATH];
        yield 'no webp' => [['webp' => false], Link::ABSOLUTE_PATH];
        yield 'its own extension' => [['extension' => true, 'webp' => false], Link::ABSOLUTE_PATH];
        yield 'png asked' => [['extension' => 'png'], Link::ABSOLUTE_PATH];
        yield 'webp asked, refused' => [['extension' => 'webp', 'webp' => false], Link::ABSOLUTE_PATH];
        yield 'no extension' => [['extension' => false], Link::ABSOLUTE_PATH];
        yield 'filters' => [['filters' => [new ThumbnailFilter(50, 50)]], Link::ABSOLUTE_PATH];
        yield 'a relative path' => [['thumbnail' => [64, 64]], Link::RELATIVE_PATH];
    }

    /** @dataProvider requests */
    public function testAnImagesLinkIsTheOneOfBefore(array $parameters, int $referenceType): void
    {
        foreach (['/images/user.jpg', '/images/logo.jpg', '/images/user.png'] as $source) {
            $image = self::image($source);
            self::assertSame(self::imageBefore($image, $parameters, $referenceType), $image->__toLink($parameters, $referenceType), $source);
            self::assertNotNull($image->__toLink($parameters, $referenceType));
        }
        self::assertNull(self::image(null)->__toLink($parameters, $referenceType));
    }

    /** @dataProvider requests */
    public function testACropsLinkIsTheOneOfBefore(array $parameters, int $referenceType): void
    {
        unset($parameters['thumbnail'], $parameters['crop'], $parameters['filters']);
        foreach ([['slug' => 'square'], ['slug' => null]] as $crop) {
            $imageCrop = new ImageCrop(self::image('/images/logo.jpg'));
            $imageCrop->setSlug($crop['slug']);
            self::assertSame(self::cropBefore($imageCrop, $parameters, $referenceType), $imageCrop->__toLink($parameters, $referenceType), json_encode($crop));
        }
    }

    private static function image(?string $source): Image
    {
        return new class($source) extends Image {
            public function __construct(private readonly ?string $publicSource)
            {
            }

            public function getSource()
            {
                return $this->publicSource;
            }

            // A crop's width and height are fractions of these (no stored file behind this image).
            public function getNaturalWidth(): ?int
            {
                return 640;
            }

            public function getNaturalHeight(): ?int
            {
                return 480;
            }
        };
    }

    /** Image::__toLink() before C4b, as it was (BaseTrait's MediaService and router). */
    private static function imageBefore(Image $image, array $routeParameters, int $referenceType): ?string
    {
        $media = BaseService::getMediaService();
        $filters = array_pop_key('filters', $routeParameters) ?? [];
        if (null === $image->getSource()) {
            return null;
        }

        $thumbnail = array_pop_key('thumbnail', $routeParameters);
        $identifier = array_pop_key('crop', $routeParameters);
        if (is_array($thumbnail)) {
            $filters[] = new ThumbnailFilter($thumbnail[0] ?? null, $thumbnail[1] ?? null);
            $identifier ??= implode('x', array_slice($thumbnail, 0, 2));
        }

        $allowWebp = array_pop_key('webp', $routeParameters) ?? true;
        $extension = array_pop_key('extension', $routeParameters) ?? true;

        if ('webp' === $extension && !$allowWebp) {
            $extension = true;
        }
        if (true === $extension) {
            if ($allowWebp) {
                $extension = 'webp';
            } else {
                $extension = $media->getExtension($image->getSource());
            }
        }

        if ($extension) {
            $routeParameters['extension'] = $extension;
        }

        $routeName = array_key_exists('extension', $routeParameters) ? 'ux_imageExtension' : 'ux_image';
        $routeParameters = array_merge($routeParameters, [
            'data' => $media->obfuscate($image->getSource(), [
                'webp' => $allowWebp,
                'identifier' => is_array($identifier) ? implode('x', $identifier) : $identifier,
                'salt' => $image->getSalt(),
            ], $filters),
        ]);

        return BaseService::getRouter()->generate($routeName, $routeParameters, $referenceType);
    }

    /** ImageCrop::__toLink() before C4b, as it was (BaseTrait's MediaService). */
    private static function cropBefore(ImageCrop $crop, array $routeParameters, int $referenceType): ?string
    {
        $media = BaseService::getMediaService();
        $allowWebp = array_pop_key('webp', $routeParameters) ?? true;
        if (array_key_exists('extension', $routeParameters)) {
            if ('webp' === $routeParameters['extension'] && !$allowWebp) {
                unset($routeParameters['extension']);
            } elseif (true === $routeParameters['extension']) {
                if ($allowWebp) {
                    $routeParameters['extension'] = 'webp';
                } else {
                    $routeParameters['extension'] = first($media->getExtensions($crop->getImage()->getSource()));
                    $routeParameters['extension'] = $media->getExtension($crop->getImage()->getSource());
                }
            } elseif (false === $routeParameters['extension']) {
                array_pop_key('extension', $routeParameters);
            }
        } elseif ($allowWebp) {
            $routeParameters['extension'] = 'webp';
        }

        $routeName = (array_key_exists('extension', $routeParameters) ? 'ux_imageExtension' : 'ux_image');
        $config = [
            'reference_type' => $referenceType,
            'identifier' => $crop->getSlug() ?? $crop->getWidth().':'.$crop->getHeight(),
            'salt' => $crop->getSalt(),
        ];

        return $media->generate($routeName, $routeParameters, $crop->getImage()->getSource(), $config);
    }
}
