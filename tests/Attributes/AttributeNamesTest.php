<?php

namespace Tests\Base\Attributes;

use Base\Attributes\AttributeReader;
use Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\Pass\AttributesPass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\Resource\SelfCheckingResourceInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The attributes the reader knows are found once, when the container is compiled (omnibridge/symfony's AttributesPass,
 * AttributeReader::discover() into the parameter AttributeReader::NAMES), no longer by each reader made, which read the
 * folders and loaded every class there for each kernel's boot. An attribute added to a folder is found; in debug the
 * container, and its list, is rebuilt when a file of those folders changes. The reader the kernel makes reads the list,
 * and holds what reading the folders gave it.
 */
final class AttributeNamesTest extends KernelTestCase
{
    private string $directory;

    private string $namespace;

    /** @var \Closure(string): void */
    private \Closure $autoloader;

    protected function setUp(): void
    {
        $id = 'D'.bin2hex(random_bytes(6));
        $this->namespace = 'Tests\\Base\\Attributes\\Discovered\\'.$id;
        $this->directory = sys_get_temp_dir().'/omnibase-attributes-'.$id;
        mkdir($this->directory);

        // The folder's classes, as an application's src/Attribute is autoloaded.
        $this->autoloader = function (string $class): void {
            if (str_starts_with($class, $this->namespace.'\\') && is_file($file = $this->directory.'/'.substr($class, \strlen($this->namespace) + 1).'.php')) {
                require $file;
            }
        };
        spl_autoload_register($this->autoloader);
    }

    protected function tearDown(): void
    {
        spl_autoload_unregister($this->autoloader);
        foreach ([...glob($this->directory.'/*') ?: [], ...glob($this->directory.'-container.php*') ?: []] as $file) {
            unlink($file);
        }
        rmdir($this->directory);

        parent::tearDown();
    }

    private function write(string $class, bool $attribute, int $mtime): void
    {
        $extends = $attribute ? ' extends \\Base\\Attributes\\AbstractAttribute' : '';
        $body = $attribute ? "    public function supports(string \$target, ?string \$targetValue = null, mixed \$object = null): bool\n    {\n        return true;\n    }\n" : '';
        file_put_contents($this->directory.'/'.$class.'.php', "<?php\n\nnamespace {$this->namespace};\n\n#[\\Attribute(\\Attribute::TARGET_PROPERTY)]\nclass {$class}{$extends}\n{\n{$body}}\n");
        touch($this->directory.'/'.$class.'.php', $mtime);
    }

    public function testANewAttributeIsFound(): void
    {
        $this->write('Stamped', true, time());
        $this->write('NotAnAttribute', false, time());

        $found = AttributeReader::discover([$this->directory]);
        self::assertSame([$this->namespace.'\\Stamped'], $found, 'the attribute, not the other class');

        $searched = AttributeReader::searchedPaths(['base.attributes.paths' => [$this->directory]]);
        self::assertSame($this->directory, end($searched), 'the configured folder after the core\'s own');
        $core = AttributeReader::discover($searched);
        self::assertContains(\Base\Database\Attribute\Trasheable::class, $core);
        self::assertContains($this->namespace.'\\Stamped', $core);
    }

    public function testTheCompiledListIsRebuiltInDebugWhenAFileChanges(): void
    {
        if (!class_exists(AttributesPass::class)) {
            self::markTestSkipped('Requires omnibridge/symfony\'s AttributesPass.');
        }
        $this->write('Stamped', true, time() - 60);

        $compile = function (): ContainerBuilder {
            $container = new ContainerBuilder();
            $container->register(AttributeReader::class, AttributeReader::class);
            $container->setParameter('kernel.project_dir', \dirname($this->directory));
            $container->setParameter('base.attributes.paths', ['%kernel.project_dir%/'.basename($this->directory)]);
            (new AttributesPass())->process($container);

            return $container;
        };

        $container = $compile();
        self::assertContains($this->namespace.'\\Stamped', $container->getParameter(AttributeReader::NAMES));
        self::assertNotContains($this->namespace.'\\Added', $container->getParameter(AttributeReader::NAMES));

        // The container's resources, as the kernel's cache checks them in debug (each resource asked whether it is
        // fresh since the cache was written): fresh until a file of the folder changes.
        $fresh = static function (ContainerBuilder $container, int $written): bool {
            foreach ($container->getResources() as $resource) {
                if ($resource instanceof SelfCheckingResourceInterface && !$resource->isFresh($written)) {
                    return false;
                }
            }

            return true;
        };
        $written = time();
        self::assertTrue($fresh($container, $written));

        $this->write('Added', true, $written + 60);
        clearstatcache();
        self::assertFalse($fresh($container, $written), 'an attribute added: the container is rebuilt in debug');

        $file = $this->directory.'-container.php';
        (new ConfigCache($file, false))->write('<?php return [];', $container->getResources());
        self::assertTrue((new ConfigCache($file, false))->isFresh(), 'not without debug (cache:clear)');

        self::assertTrue($fresh($compile(), $written + 90), 'rebuilt after it: fresh again');
        touch($this->directory.'/Stamped.php', $written + 120);
        clearstatcache();
        self::assertFalse($fresh($compile(), $written + 90), 'an attribute changed: rebuilt as well');

        // The rebuilt container's list holds it (a new process: ClassFinder's own memory is per process).
        $names = AttributeReader::discover([$this->directory.'/Added.php', $this->directory.'/Stamped.php']);
        self::assertSame([$this->namespace.'\\Added', $this->namespace.'\\Stamped'], $names);
    }

    public function testTheReaderMadeByTheKernelReadsTheList(): void
    {
        if (!class_exists('App\\Kernel') || !class_exists(AttributesPass::class)) {
            self::markTestSkipped('Requires the host application kernel and omnibridge/symfony\'s AttributesPass.');
        }
        self::bootKernel();
        $container = static::getContainer();
        $names = $container->getParameter(AttributeReader::NAMES);

        self::assertFalse($container->initialized(AttributeReader::class), 'no reader made by the boot');
        $reader = AttributeReader::getInstance();
        self::assertSame($container->get(AttributeReader::class), $reader, 'the container\'s, made when first asked');
        self::assertSame($names, $reader->getAttributeNames());
        self::assertSame(AttributeReader::discover($reader->getPaths()), $names, 'what reading the folders gives');
    }
}
