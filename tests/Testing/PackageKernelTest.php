<?php

namespace Tests\Base\Testing;

use Base\DependencyInjection\Compiler\DiscoveryPass;
use Base\Testing\PackageKernel;
use Base\Testing\WithoutPackages;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Base\Testing (7.2): a package's bundle on the core alone, Doctrine absent from the process (WithoutPackages), on
 * omnibridge/eloquent (PackageKernel) - its src/Entity discovered with no configuration (Base\Discovery's `entities`),
 * a feature's folder left out by base.entity_folders (its class stands on a package that is not installed: never
 * loaded), its enumeration a column type, its interface's #[ResolveTargetEntity] resolving the association; its table
 * made by omnibridge:schema:create, a row written and read back through the unit of work.
 */
final class PackageKernelTest extends TestCase
{
    private const SHOP = __DIR__.'/../Fixtures/Discovery/packages/acme/shop';

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAPackageRunsOnEloquentWithoutDoctrine(): void
    {
        if (!class_exists(\Base\BaseBundle::class) || !class_exists('Omnibridge\\Eloquent\\Omnibase\\EloquentPersistence') || !\extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Needs omnibridge/symfony, omnibridge/eloquent and pdo_sqlite.');
        }
        WithoutPackages::install();
        self::assertFalse(class_exists(\Doctrine\ORM\EntityManager::class));
        self::assertTrue(interface_exists(\Doctrine\Common\Collections\Collection::class), 'doctrine/collections is no ORM: kept');
        spl_autoload_register(static function (string $class): void {
            if (str_starts_with($class, 'Acme\\Shop\\') && is_file($file = self::SHOP.'/src/'.str_replace('\\', '/', substr($class, \strlen('Acme\\Shop\\'))).'.php')) {
                require_once $file;
            }
        });
        $shop = (string) realpath(self::SHOP);

        $kernel = new PackageKernel([\Acme\Shop\ShopBundle::class], ['base' => ['entity_folders' => ['exclude' => [$shop.'/src/Entity/Feature']]]], name: 'package-kernel-test');
        $kernel->clean();
        $kernel->createSchema();
        $container = $kernel->getContainer();
        self::assertSame('eloquent', $container->getParameter('base.persistence'));
        $entities = $container->getParameter(DiscoveryPass::ENTITIES);
        self::assertArrayHasKey($shop.'/src/Entity', $entities['paths'], json_encode([$entities, $container->getParameter('kernel.bundles_metadata')]));
        self::assertSame(['Acme\\Shop\\Model\\CustomerInterface' => 'App\\Entity\\User'], $entities['resolve_target_entities']);

        $unitOfWork = $container->get(\Omnibridge\Persistence\UnitOfWork::class);
        $order = new \Acme\Shop\Entity\Order();
        $unitOfWork->persist($order);
        $unitOfWork->flush();

        $pdo = $container->get('omnibridge.eloquent.connection')->getPdo();
        self::assertSame([['status' => 'open', 'customer_id' => null]], $pdo->query('SELECT status, customer_id FROM acme_order')->fetchAll(\PDO::FETCH_ASSOC));
        $columns = $pdo->query("SELECT sql FROM sqlite_master WHERE name = 'acme_order'")->fetchColumn();
        self::assertStringContainsString('REFERENCES "user"', (string) $columns, 'the interface resolved to the application\'s user');
        self::assertFalse(class_exists('Acme\\Shop\\Entity\\Feature\\Subscription', false), 'the feature that is off was never loaded');
        self::assertSame([], array_filter(get_declared_classes(), static fn (string $class): bool => str_starts_with($class, 'Doctrine\\ORM\\')));

        $kernel->shutdown();
        $kernel->clean();
    }
}
