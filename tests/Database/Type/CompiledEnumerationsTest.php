<?php

namespace Tests\Base\Database\Type;

use Base\Database\Type\Enumerations;
use Base\Enum\ConnectionState;
use Doctrine\DBAL\Types\Type;
use Omnibridge\Doctrine\Omnibase\EnumTypeAdapter;
use Omnibridge\Doctrine\Omnibase\Types;
use Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\Pass\EnumerationsPass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Config\Resource\SelfCheckingResourceInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The enumerations each kernel's boot registers with DBAL (the core's src/Enum and the application's) are found once,
 * when the container is compiled (omnibridge/symfony's EnumerationsPass, Types::enumerations() into a parameter), no
 * longer by each boot, which read both folders and loaded every class there (Types::register() given the folders). In
 * debug the container, and its list, is rebuilt when a file of those folders changes, or when the application's is made.
 */
final class CompiledEnumerationsTest extends KernelTestCase
{
    private string $projectDir;

    private string $namespace;

    /** @var \Closure(string): void */
    private \Closure $autoloader;

    protected function setUp(): void
    {
        if (!class_exists(EnumerationsPass::class)) {
            self::markTestSkipped('Requires omnibridge/symfony\'s EnumerationsPass.');
        }
        $id = 'E'.bin2hex(random_bytes(6));
        $this->namespace = 'Tests\\Base\\Database\\Type\\Compiled\\'.$id;
        $this->projectDir = sys_get_temp_dir().'/omnibase-enumerations-'.$id;
        mkdir($this->projectDir);

        // The application's src/Enum, autoloaded as an application's is.
        $this->autoloader = function (string $class): void {
            if (str_starts_with($class, $this->namespace.'\\') && is_file($file = $this->projectDir.'/src/Enum/'.substr($class, \strlen($this->namespace) + 1).'.php')) {
                require $file;
            }
        };
        spl_autoload_register($this->autoloader);
    }

    protected function tearDown(): void
    {
        if (isset($this->autoloader)) {
            spl_autoload_unregister($this->autoloader);
            foreach (glob($this->projectDir.'/src/Enum/*') ?: [] as $file) {
                unlink($file);
            }
            @rmdir($this->projectDir.'/src/Enum');
            @rmdir($this->projectDir.'/src');
            rmdir($this->projectDir);
        }

        parent::tearDown();
    }

    private function write(string $class, int $mtime): void
    {
        file_put_contents($this->projectDir.'/src/Enum/'.$class.'.php', "<?php\n\nnamespace {$this->namespace};\n\nclass {$class} extends \\Base\\Database\\Type\\EnumType\n{\n    public const ON = 'ON';\n}\n");
        touch($this->projectDir.'/src/Enum/'.$class.'.php', $mtime);
    }

    private function compile(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', $this->projectDir);
        (new EnumerationsPass())->process($container);

        return $container;
    }

    private static function fresh(ContainerBuilder $container, int $written): bool
    {
        foreach ($container->getResources() as $resource) {
            if ($resource instanceof SelfCheckingResourceInterface && !$resource->isFresh($written)) {
                return false;
            }
        }

        return true;
    }

    public function testTheListIsCompiledAndRebuiltInDebugWhenAFolderChanges(): void
    {
        $written = time();

        // No src/Enum yet: the core's alone, rebuilt once the folder is made.
        $container = $this->compile();
        $core = $container->getParameter(EnumerationsPass::PARAMETER);
        self::assertSame(ConnectionState::class, $core[ConnectionState::getStaticName()] ?? null);
        self::assertSame(Types::enumerations([Types::coreEnumerationDirectory()]), $core);
        self::assertTrue(self::fresh($container, $written));
        mkdir($this->projectDir.'/src/Enum', 0777, true);
        clearstatcache();
        self::assertFalse(self::fresh($container, $written), 'the application\'s folder made: rebuilt in debug');

        $this->write('Stamping', $written - 60);
        $container = $this->compile();
        $names = $container->getParameter(EnumerationsPass::PARAMETER);
        $class = $this->namespace.'\\Stamping';
        self::assertSame($class, $names['stamping'] ?? null, 'the application\'s enumeration, by its name');
        self::assertSame(Types::enumerations(EnumerationsPass::directories($this->projectDir)), $names, 'what reading the folders gives');
        self::assertTrue(self::fresh($container, $written + 30));

        $this->write('Added', $written + 60);
        clearstatcache();
        self::assertFalse(self::fresh($container, $written + 30), 'an enumeration added: rebuilt in debug');
    }

    public function testTheBootRegistersTheCompiledList(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel.');
        }
        self::bootKernel();
        $container = static::getContainer();
        if (!$container->getParameter('base.database.use_custom') || !$container->has('doctrine.orm.entity_manager')) {
            self::markTestSkipped('The boot registers no type without Doctrine and base.database.use_custom.');
        }

        $names = $container->getParameter(EnumerationsPass::PARAMETER);
        self::assertSame(Types::enumerations(EnumerationsPass::directories($container->getParameter('kernel.project_dir'))), $names);
        self::assertNotEmpty($names);
        foreach ($names as $name => $class) {
            self::assertTrue(Type::hasType($name), $name.' registered');
            $type = Type::getType($name);
            if ($type instanceof EnumTypeAdapter && $type->enumeration() === $class) {
                self::assertSame($class, Enumerations::classOf($name));
            }
        }
        self::assertSame(ConnectionState::class, Enumerations::classOf(ConnectionState::getStaticName()));
    }
}
