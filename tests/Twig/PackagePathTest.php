<?php

namespace Tests\Base\Twig;

use Base\Twig\Loader\FilesystemLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The package is omniscient/omnibase since its rename from glitchr/omnibase:
 * Composer installs it in vendor/omniscient/omnibase, and an application that
 * still names vendor/glitchr/omnibase/templates as base.twig.default_path
 * (most sites' base.yaml did) keeps its pages - a path that is not there is
 * the package's own templates. The default needs no vendor path at all.
 */
final class PackagePathTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/package-path-'.bin2hex(random_bytes(4));
        foreach (['project/templates', 'project/src/Controller', 'project/public', 'project/vendor/symfony/twig-bridge/Resources/views'] as $directory) {
            mkdir($this->root.'/'.$directory, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->root);
    }

    public function testTheOldVendorPathIsThePackagesTemplates(): void
    {
        $templates = realpath(__DIR__.'/../../templates');
        $loader = new FilesystemLoader(new \Twig\Loader\FilesystemLoader([], $this->root.'/project'), new ParameterBag([
            'base.twig.use_custom' => true,
            'base.twig.default_path' => $this->root.'/project/vendor/glitchr/omnibase/templates',
            'base.twig.paths' => [],
        ]), $this->root.'/project');

        self::assertSame([$templates], array_map('realpath', $loader->getPaths('!Base')));
        self::assertTrue($loader->exists('@Base/form/form_div_layout.html.twig'));
    }

    public function testTheDefaultIsThePackagesTemplates(): void
    {
        $config = (new \Symfony\Component\Config\Definition\Processor())->processConfiguration(new \Base\DependencyInjection\BaseConfiguration(), []);

        self::assertSame(realpath(__DIR__.'/../../templates'), realpath($config['twig']['default_path']));
    }
}
