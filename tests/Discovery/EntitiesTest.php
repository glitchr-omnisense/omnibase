<?php

namespace Tests\Base\Discovery;

use Base\Database\Schema\EntitySchema;
use Base\Discovery;
use PHPUnit\Framework\TestCase;

/**
 * Base\Discovery's `entities` kind (omnibase 7.2): a package's entities, enumerations and target interfaces found by
 * where they are - its src/Entity, its src/Enum, the interfaces of its src/ carrying #[ResolveTargetEntity] - on any
 * host and whichever persistence, with no configuration; the folders a package adds or leaves out by configuration
 * (the `entity_folders` option) on top. The fixture is a Composer vendor folder (tests/Fixtures/Discovery/packages):
 * acme/shop requires the core, acme/tool does not.
 */
final class EntitiesTest extends TestCase
{
    private const FIXTURES = __DIR__.'/../Fixtures/Discovery';

    public static function setUpBeforeClass(): void
    {
        // The fixture package's classes, as Composer would autoload them.
        spl_autoload_register(static function (string $class): void {
            if (str_starts_with($class, 'Acme\\Shop\\')) {
                $file = self::FIXTURES.'/packages/acme/shop/src/'.str_replace('\\', '/', substr($class, \strlen('Acme\\Shop\\'))).'.php';
                if (is_file($file)) {
                    require_once $file;
                }
            }
        });
    }

    private static function shop(): string
    {
        return (string) realpath(self::FIXTURES.'/packages/acme/shop');
    }

    public function testThePackagesThatRequireTheCoreAreFoundInComposersVendor(): void
    {
        self::assertSame([self::shop() => 'Acme\\Shop'], Discovery::packages(self::FIXTURES.'/packages'));
        self::assertSame([], Discovery::packages(self::FIXTURES.'/no-vendor'), 'no installed.json: no package');
        self::assertSame(['directories' => ['%package%/src/Entity', '%kernel.project_dir%/src/Entity'], 'requires' => 'omnisense/omnibase'], Discovery::registry('entities'));
    }

    public function testEachPackagesEntityFolderWithItsNamespaceTheCoresFirst(): void
    {
        $project = (string) realpath(self::FIXTURES.'/project');
        $folders = Discovery::entities($project, Discovery::packages(self::FIXTURES.'/packages'), [self::FIXTURES.'/missing' => 'Acme\\Missing']);

        self::assertSame([
            EntitySchema::coreDirectory('Entity') => 'Base\\Entity',
            self::shop().'/src/Entity' => 'Acme\\Shop\\Entity',
            $project.'/src/Entity' => 'App\\Entity',
        ], $folders, 'a folder that does not exist is left out');

        $included = Discovery::entities($project, [], [self::shop().'/src/Entity/Feature' => null]);
        self::assertSame('Acme\\Shop\\Entity\\Feature', $included[self::shop().'/src/Entity/Feature'], 'an included folder\'s namespace read from its files');
    }

    public function testAPackagesEnumerationsAndTargets(): void
    {
        $packages = Discovery::packages(self::FIXTURES.'/packages');

        self::assertSame('Acme\\Shop\\Enum\\OrderStatus', Discovery::enumerations(self::FIXTURES.'/project', null, $packages)['acme_order_status'] ?? null);
        self::assertArrayNotHasKey('acme_order_status', Discovery::enumerations(self::FIXTURES.'/project'));
        self::assertSame(['Acme\\Shop\\Model\\CustomerInterface' => 'App\\Entity\\User'], Discovery::targets($packages));
        self::assertSame('Acme\\Shop\\Enum\\OrderStatus', EntitySchema::types(['project_dir' => self::FIXTURES.'/project', 'packages' => $packages])['acme_order_status'] ?? null);
    }

    public function testThePackagesEntitiesDescribedAFeaturesFolderLeftOutUnloaded(): void
    {
        $project = self::FIXTURES.'/project';
        $packages = Discovery::packages(self::FIXTURES.'/packages');
        $options = ['project_dir' => $project, 'packages' => $packages, 'exclude' => [self::shop().'/src/Entity/Feature']];

        $description = EntitySchema::describe(array_keys(Discovery::entities($project, $packages)), $options);

        self::assertArrayHasKey('Acme\\Shop\\Entity\\Order', $description->entities);
        self::assertArrayNotHasKey('Acme\\Shop\\Entity\\Feature\\Subscription', $description->entities);
        self::assertFalse(class_exists('Acme\\Shop\\Entity\\Feature\\Subscription', false), 'an excluded class is never loaded (its parent is missing)');
        self::assertSame('acme_order_status', $description->table('acme_order')->columns['status']->type);
        self::assertArrayHasKey('acme_order_status', EntitySchema::columnTypes($options)->enumerations);
        self::assertSame('App\\Entity\\User', $description->entity('Acme\\Shop\\Entity\\Order')->relation('customer')->target, 'the interface resolved to the class it declares');
        self::assertSame(['user'], array_values(array_map(static fn ($key) => $key->table, $description->table('acme_order')->foreignKeys)));
    }

    public function testTheApplicationsTargetPrevailsOverThePackagesDefault(): void
    {
        $project = self::FIXTURES.'/project';
        $packages = Discovery::packages(self::FIXTURES.'/packages');
        $options = ['project_dir' => $project, 'packages' => $packages, 'exclude' => [self::shop().'/src/Entity/Feature'], 'resolve_target_entities' => ['Acme\\Shop\\Model\\CustomerInterface' => 'Base\\Entity\\Thread']];

        $description = EntitySchema::describe(array_keys(Discovery::entities($project, $packages)), $options);

        self::assertSame('Base\\Entity\\Thread', $description->entity('Acme\\Shop\\Entity\\Order')->relation('customer')->target);
        self::assertSame(['thread'], array_values(array_map(static fn ($key) => $key->table, $description->table('acme_order')->foreignKeys)));
    }
}
