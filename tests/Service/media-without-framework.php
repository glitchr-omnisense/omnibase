<?php

/*
 * omnibase's image rule where there is no framework (audit "lesringostars
 * face au master plan", R6): run by docker/bin/bare for tests/BareTest.php,
 * with Composer's autoloader alone - no kernel, no container, no bundle -
 * and bare reports every class and file of the framework loaded on the way
 * (none may be).
 *
 * MediaService is built by hand, as a host without Symfony builds it: a plain
 * router over Base\Service\ImageRoute::ROUTES (Base\Contract\MediaRouter),
 * the Obfuscator on a folder, omnibase's Flysystem over a PSR-11 container of
 * two local storages, the parameters in an ArrayParameterBag, Imagine. Then a
 * thumbnail's address is made, read back, and its file made in the local
 * cache, as ImageRoute does before it sends it (sending it is HttpFoundation's,
 * the host's). Returns what it found.
 */

use Base\Contract\MediaRouter;
use Base\Imagine\Filter\Basic\ThumbnailFilter;
use Base\Imagine\Filter\Format\BitmapFilter;
use Base\Service\ArrayParameterBag;
use Base\Service\Flysystem;
use Base\Service\ImageRoute;
use Base\Service\MediaService;
use Base\Service\Model\Obfuscator\Compression\NullCompression;
use Base\Service\Obfuscator;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Psr\Container\ContainerInterface;

// A router of the image routes alone, as a host declares them.
final class BareMediaRouter implements MediaRouter
{
    public function generate(string $name, array $parameters = [], int $referenceType = 1): string
    {
        [$path] = ImageRoute::ROUTES[$name] ?? throw new InvalidArgumentException($name);
        foreach ($parameters as $key => $value) {
            if (str_contains($path, '{'.$key.'}')) {
                $path = str_replace('{'.$key.'}', (string) $value, $path);
                unset($parameters[$key]);
            }
        }

        return $path.($parameters ? '?'.http_build_query($parameters) : '');
    }

    public function getRouteMatch(?string $routeUrl = null): ?array
    {
        $path = (string) parse_url((string) $routeUrl, PHP_URL_PATH);
        foreach (ImageRoute::ROUTES as $name => [$pattern]) {
            $regex = '#^'.preg_replace_callback('/\\\\\{(\w+)\\\\\}/', static fn ($m) => '(?P<'.$m[1].'>'.('data' === $m[1] ? '.+' : '[^/]+').')', preg_quote($pattern, '#')).'$#';
            if (preg_match($regex, $path, $match)) {
                return ['_route' => $name] + array_filter($match, 'is_string', ARRAY_FILTER_USE_KEY);
            }
        }

        return null;
    }

    public function getRouteName(?string $routeUrl = null): ?string
    {
        return $this->getRouteMatch($routeUrl)['_route'] ?? null;
    }

    public function getAssetUrl(string $nameOrUrl, ?string $packageName = null): string
    {
        return '/'.ltrim($nameOrUrl, '/');
    }

    public function getUrl(?string $nameOrUrl = null, array $parameters = [], int $referenceType = 1): string
    {
        return (string) $nameOrUrl;
    }
}

$root = sys_get_temp_dir().'/omnibase-bare-media-'.getmypid();
@mkdir($root.'/source', 0777, true);
$source = $root.'/source/photo.png';
$picture = new Imagick();
$picture->newImage(400, 200, 'steelblue', 'png');
$picture->writeImage($source);

$storages = new class($root) implements ContainerInterface {
    private array $operators;

    public function __construct(string $root)
    {
        $this->operators = [
            'local.cache' => new Filesystem(new LocalFilesystemAdapter($root.'/cache')),
            'local.uploads' => new Filesystem(new LocalFilesystemAdapter($root.'/uploads')),
        ];
    }

    public function get(string $id): mixed
    {
        return $this->operators[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->operators[$id]);
    }
};

$parameters = new ArrayParameterBag([
    'base.images.timeout' => 60,
    'base.images.fallback' => false,
    'base.images.max_resolution' => Base\Imagine\Filter\Basic\Definition\UltraHighDefinitionFilter::class,
    'base.images.max_quality' => 0.8,
    'base.images.enable_webp' => true,
    'base.images.no_image' => [],
    'base.images.debug' => false,
    'base.uploader.storage' => 'local.uploads',
    'base.twig.editor.storage' => null,
    'base.obfuscator.uuid' => '6ba7b811-9dad-11d1-80b4-00c04fd430c8',
    'base.obfuscator.compression' => 'null',
    'base.obfuscator.max_length' => 0,
    'base.obfuscator.level' => -1,
    'base.obfuscator.encoding' => null,
]);

$obfuscator = new Obfuscator($parameters, $root.'/obfuscator');
$obfuscator->addCompression(new NullCompression());

$router = new BareMediaRouter();
$media = new MediaService(
    new Twig\Environment(new Twig\Loader\ArrayLoader()),
    $router,
    $obfuscator,
    new Flysystem($storages),
    $parameters,
    new Imagine\Imagick\Imagine(),
    new Base\Imagine\Svg\Imagine(),
    null,
);

$thumbnail = $media->thumbnail($source, 100, 100, ['webp' => false]);
$match = $router->getRouteMatch($thumbnail);
$config = $media->resolve($match['data']);
$filters = array_map(static fn ($filter) => $filter::class, $config['filters']);
$file = $media->filter($config['path'], ['local_cache' => true, 'output' => pathinfo_extension($match['data'].'/image', $match['extension'])], new BitmapFilter(null, $config['options'], $config['filters']));
$size = getimagesize($file);

$result = [
    'thumbnail, its route' => $match['_route'],
    'thumbnail, its address' => (bool) preg_match('#^/images/([0-9a-f]{2}/){5}[0-9a-f]{22}/image\.png$#', $thumbnail),
    'thumbnail, the same address again' => $thumbnail === $media->thumbnail($source, 100, 100, ['webp' => false]),
    'thumbnail, read back' => [$config['path'] === $source, $filters, (float) $config['options']['quality']],
    'thumbnail, its file' => [$size[0], $size[1], $size['mime']],
    'thumbnail, kept in the local cache' => str_starts_with($file, $root.'/cache/'),
    'image, webp' => (bool) preg_match('#^/images/([0-9a-f]{2}/){5}[0-9a-f]{22}/image\.webp$#', (string) $media->image($source, ['webp' => true])),
    'image, extension' => $media->getExtension($source),
];

exec('rm -rf '.escapeshellarg($root));

return $result;
