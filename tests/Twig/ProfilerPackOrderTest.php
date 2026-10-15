<?php

namespace Tests\Base\Twig;

use Base\Twig\Loader\FilesystemLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Filesystem\Filesystem;

/**
 * This bundle's override of WebProfilerBundle's templates came first on every
 * site, ahead of glitchr/symfony-profiler-pack's: a site that installed the
 * pack (for its toolbar that moves under a finger) still had the old one.
 * With the pack, the pack comes first and this bundle's copy right after it,
 * for the templates the pack does not have; without it, nothing changes.
 */
final class ProfilerPackOrderTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/profiler-pack-order-'.bin2hex(random_bytes(4));
        foreach ([
            'base/bundles/WebProfilerBundle', 'base/notifier',
            'project/src/Controller', 'project/public', 'project/templates',
            'project/vendor/symfony/twig-bridge/Resources/views',
            'project/vendor/glitchr/symfony-profiler-pack/templates/bundles/WebProfilerBundle',
            'project/vendor/symfony/web-profiler-bundle/Resources/views',
        ] as $directory) {
            mkdir($this->root.'/'.$directory, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->root);
    }

    public function testThePackComesBeforeThisBundlesCopy(): void
    {
        $paths = $this->webProfilerPaths(withPack: true);

        self::assertSame([
            $this->root.'/project/vendor/glitchr/symfony-profiler-pack/templates/bundles/WebProfilerBundle',
            $this->root.'/base/bundles/WebProfilerBundle',
            $this->root.'/project/vendor/symfony/web-profiler-bundle/Resources/views',
        ], $paths);
    }

    public function testWithoutThePackThisBundlesCopyComesFirst(): void
    {
        $paths = $this->webProfilerPaths(withPack: false);

        self::assertSame([
            $this->root.'/base/bundles/WebProfilerBundle',
            $this->root.'/project/vendor/symfony/web-profiler-bundle/Resources/views',
        ], $paths);
    }

    /** @return list<string> */
    private function webProfilerPaths(bool $withPack): array
    {
        $inner = new \Twig\Loader\FilesystemLoader([], $this->root.'/project');
        if ($withPack) {
            $inner->addPath($this->root.'/project/vendor/glitchr/symfony-profiler-pack/templates/bundles/WebProfilerBundle', 'WebProfiler');
        }
        $inner->addPath($this->root.'/project/vendor/symfony/web-profiler-bundle/Resources/views', 'WebProfiler');

        $loader = new FilesystemLoader($inner, new ParameterBag([
            'base.twig.use_custom' => true,
            'base.twig.default_path' => $this->root.'/base',
            'base.twig.paths' => [],
        ]), $this->root.'/project');

        return $loader->getPaths('WebProfiler');
    }
}
