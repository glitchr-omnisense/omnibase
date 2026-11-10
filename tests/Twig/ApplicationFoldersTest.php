<?php

namespace Tests\Base\Twig;

use Base\Twig\Loader\FilesystemLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Filesystem\Filesystem;

/**
 * omnibase's Twig loader on an application that has none of the folders it reads (templates/, src/Controller/,
 * public/, symfony/twig-bridge's views), and with base.twig.use_custom left unset (it has no default): the loader is
 * built, @Base is the package's templates, and the unset switch is off, as the switch's other readers take it. Until
 * now the first missing folder stopped every page ("The .../src/Controller directory does not exist"), and the unset
 * switch was a TypeError (omnibridge/parity's "What each host needs": empty folders and base.yaml).
 */
final class ApplicationFoldersTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/application-folders-'.bin2hex(random_bytes(4));
        mkdir($this->root.'/src', 0777, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->root);
    }

    public function testAnApplicationWithNoneOfTheFoldersIsServed(): void
    {
        $loader = new FilesystemLoader(new \Twig\Loader\FilesystemLoader([], $this->root), new ParameterBag([
            'base.twig.use_custom' => null,
            'base.twig.default_path' => null,
            'base.twig.paths' => [],
        ]), $this->root);

        self::assertSame([realpath(__DIR__.'/../../templates')], array_map('realpath', $loader->getPaths('!Base')), '@!Base the package\'s templates');
        self::assertSame([], $loader->getPaths('Controller'), 'no src/Controller, no @Controller');
        self::assertSame([], $loader->getPaths('Public'));
        self::assertFalse((new \ReflectionProperty($loader, 'useCustomLoader'))->getValue($loader), 'unset is off');
    }

    public function testTheFoldersThereAreRead(): void
    {
        foreach (['templates', 'src/Controller', 'public'] as $directory) {
            mkdir($this->root.'/'.$directory, 0777, true);
        }
        $loader = new FilesystemLoader(new \Twig\Loader\FilesystemLoader([], $this->root), new ParameterBag([
            'base.twig.use_custom' => true,
            'base.twig.default_path' => null,
            'base.twig.paths' => [],
        ]), $this->root);

        self::assertSame([realpath($this->root.'/src/Controller')], array_map('realpath', $loader->getPaths('Controller')));
        self::assertContains(realpath($this->root.'/templates'), array_map('realpath', $loader->getPaths()));
        self::assertTrue((new \ReflectionProperty($loader, 'useCustomLoader'))->getValue($loader));
        self::assertTrue($loader->exists('@Base/form/form_div_layout.html.twig'));
    }
}
