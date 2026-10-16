<?php

namespace Tests\Base\Twig;

use Base\Twig\Loader\FilesystemLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @Base came from Symfony's bundle path alone, src/Resources/views, a link to
 * templates/: a copy of the package without its links (Composer's path
 * repository with symlink: false, the harness) had no @Base at all, and every
 * page including one of this bundle's partials failed ("There are no
 * registered paths for namespace Base"). The loader declares it.
 */
final class BaseNamespaceTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/base-namespace-'.bin2hex(random_bytes(4));
        foreach (['base/templates/notifier', 'base/src/Resources', 'project/src/Controller', 'project/public', 'project/templates/bundles/BaseBundle', 'project/vendor/symfony/twig-bridge/Resources/views'] as $directory) {
            mkdir($this->root.'/'.$directory, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->root);
    }

    public function testACopyWithoutItsLinksStillHasBase(): void
    {
        $loader = $this->loader(new \Twig\Loader\FilesystemLoader([], $this->root.'/project'));

        self::assertSame([$this->root.'/project/templates/bundles/BaseBundle', $this->root.'/base/templates'], $loader->getPaths('Base'));
        self::assertSame([$this->root.'/base/templates'], $loader->getPaths('!Base'));
    }

    public function testThroughTheLinkNothingIsAddedTwice(): void
    {
        symlink('../../templates/', $this->root.'/base/src/Resources/views');
        $inner = new \Twig\Loader\FilesystemLoader([], $this->root.'/project');
        $inner->addPath($this->root.'/project/templates/bundles/BaseBundle', 'Base');
        $inner->addPath($this->root.'/base/src/Resources/views', 'Base');
        $inner->addPath($this->root.'/base/src/Resources/views', '!Base');

        $loader = $this->loader($inner);

        self::assertSame([$this->root.'/project/templates/bundles/BaseBundle', $this->root.'/base/src/Resources/views'], $loader->getPaths('Base'));
        self::assertSame([$this->root.'/base/src/Resources/views'], $loader->getPaths('!Base'));
    }

    private function loader(\Twig\Loader\FilesystemLoader $inner): FilesystemLoader
    {
        return new FilesystemLoader($inner, new ParameterBag([
            'base.twig.use_custom' => true,
            'base.twig.default_path' => $this->root.'/base/templates',
            'base.twig.paths' => [],
        ]), $this->root.'/project');
    }
}
