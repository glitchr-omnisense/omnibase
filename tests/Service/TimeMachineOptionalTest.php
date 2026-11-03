<?php

namespace Tests\Base\Service;

use Omnibridge\Symfony\Omnibase\DependencyInjection\BaseConfiguration;
use Base\Service\TimeMachineInterface;
use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * glitchr/backup-manager is a suggestion since 4.0, no longer a requirement: the TimeMachine
 * (Base\Service\TimeMachine extends its Manager) and its four commands are registered only where
 * it is installed, and the configuration's default excludes are TimeMachineInterface's, read
 * without loading the TimeMachine. Without the package, a definition of the TimeMachine would
 * fail the container, and reading TimeMachine::DEFAULT_EXCLUDES would be a fatal error (its
 * parent class missing).
 *
 * "Without" is a PHP process of its own whose autoloader finds no Backup\ class (this one has
 * the package installed, the core's require-dev): the configuration tree is built and the core's
 * service files loaded as BaseExtension::load() loads them.
 */
final class TimeMachineOptionalTest extends TestCase
{
    private const SERVICES = ['Base\\Service\\TimeMachine', 'time_machine', TimeMachineInterface::class, 'Backup\\Manager\\Manager', 'Base\\Console\\Command\\TimeMachineSnapshotCommand', 'Base\\Console\\Command\\TimeMachineSnapshotBackupCommand', 'Base\\Console\\Command\\TimeMachineSnapshotRestoreCommand', 'Base\\Console\\Command\\TimeMachineStorageCommand'];

    private const CHECKS = <<<'PHP'
        $tree = (new Omnibridge\Symfony\Omnibase\DependencyInjection\BaseConfiguration())->getConfigTreeBuilder()->buildTree();
        $config = (new Symfony\Component\Config\Definition\Processor())->process($tree, []);
        $container = new Symfony\Component\DependencyInjection\ContainerBuilder();
        $loader = new Symfony\Component\DependencyInjection\Loader\PhpFileLoader($container, new Symfony\Component\Config\FileLocator(CONFIG));
        foreach (['services-list.php', 'services.php', 'services-public.php', 'services-fix.php', 'services-decoration.php', 'services-list-contracts.php'] as $file) {
            $loader->load($file);
        }
        $services = [];
        foreach (SERVICES as $id) {
            $services[$id] = $container->has($id);
        }
        echo json_encode([
            'manager' => class_exists('Backup\Manager\Manager'),
            'time_machine_loaded' => class_exists('Base\Service\TimeMachine', false),
            'excludes' => $config['time_machine']['excludes'],
            'services' => $services,
        ]);
        PHP;

    /** @return array{manager: bool, time_machine_loaded: bool, excludes: list<string>, services: array<string, bool>} */
    private static function withoutBackupManager(): array
    {
        // The autoloader that loads the core: a site's PHPUnit may come with Composer's ClassLoader of its own
        // (symfony/phpunit-bridge's bin/.phpunit), registered too, which knows nothing of Base\.
        $vendors = array_keys(array_filter(ClassLoader::getRegisteredLoaders(), static fn (ClassLoader $loader) => false !== $loader->findFile(BaseConfiguration::class)));
        self::assertNotEmpty($vendors, 'No registered Composer autoloader loads the core.');
        $autoload = $vendors[0].'/autoload.php';
        $code = 'const CONFIG = '.var_export(\dirname((new \ReflectionClass(\Omnibridge\Symfony\Omnibase\BaseBundle::class))->getFileName(), 3).'/config/omnibase', true).'; const SERVICES = '.var_export(self::SERVICES, true).';'
            .'$composer = require '.var_export($autoload, true).'; $composer->unregister();'
            .'spl_autoload_register(static function (string $class) use ($composer): void { if (!str_starts_with($class, "Backup\\\\")) { $composer->loadClass($class); } }, true, true);'
            .self::CHECKS;
        $process = proc_open([\PHP_BINARY, '-d', 'display_errors=stderr', '-d', 'memory_limit=512M', '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        $status = proc_close($process);
        self::assertSame(0, $status, $err.$out);
        $report = json_decode($out, true);
        self::assertIsArray($report, $err.$out);

        return $report;
    }

    public function testWithoutThePackageNothingOfTheTimeMachineIsRegistered(): void
    {
        $report = self::withoutBackupManager();

        self::assertFalse($report['manager'], 'The process was meant to have no Backup\\ class.');
        self::assertFalse($report['time_machine_loaded']);
        self::assertSame(TimeMachineInterface::DEFAULT_EXCLUDES, $report['excludes']);
        self::assertSame(array_fill_keys(self::SERVICES, false), $report['services']);
    }

    public function testWithThePackageTheTimeMachineAndItsCommandsAreRegistered(): void
    {
        if (!class_exists('Backup\\Manager\\Manager')) {
            self::markTestSkipped('glitchr/backup-manager is not installed.');
        }
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator(\dirname((new \ReflectionClass(\Omnibridge\Symfony\Omnibase\BaseBundle::class))->getFileName(), 3).'/config/omnibase'));
        foreach (['services-list.php', 'services.php', 'services-public.php', 'services-fix.php', 'services-decoration.php', 'services-list-contracts.php'] as $file) {
            $loader->load($file);
        }
        foreach (self::SERVICES as $id) {
            self::assertTrue($container->has($id), $id);
        }
        self::assertTrue($container->getDefinition('Backup\\Manager\\Manager')->isAbstract());

        $config = (new \Symfony\Component\Config\Definition\Processor())->process((new BaseConfiguration())->getConfigTreeBuilder()->buildTree(), []);
        self::assertSame(TimeMachineInterface::DEFAULT_EXCLUDES, $config['time_machine']['excludes']);
    }
}
