<?php

namespace Tests\Base\Bridge\Doctrine;

use Base\Bridge\Doctrine\MappingDriver;
use Base\Database\Schema\Describer;
use Base\Database\Schema\DiscriminatorRule;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\ResolveTargetEntityListener;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Omnibridge\Doctrine\DeclarationDriver;
use Omnibridge\Doctrine\MetadataDescription;
use Omnibridge\Schema\Database\MySqlSchema;
use Omnibridge\Schema\Description;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The structure described outside Doctrine (audit "lesringostars face au master
 * plan", R14), on the host application's entities (the harness's, or an
 * application's own when its suite runs this file from vendor/):
 *
 * - Doctrine reads it: the entity manager's driver is omnibridge/doctrine's
 *   DeclarationDriver, under omnibase's MappingDriver;
 * - the description Base\Database\Schema\Describer builds without any ORM
 *   from the entities' folders (their declarations, omnibase's naming and
 *   rules) is the description of what Doctrine maps (MetadataDescription:
 *   its metadata, the tables its SchemaTool creates), entity by entity and
 *   table by table;
 * - on MySQL (lesringostars), it is the database's own schema: every table,
 *   column (SQL type, nullable, default, auto-increment), primary key, index
 *   and foreign key read from information_schema, nothing of Doctrine asked.
 */
final class SchemaDescriptionTest extends KernelTestCase
{
    /** Tables of the database the mapping does not describe. */
    private const FOREIGN_TABLES = ['doctrine_migration_versions', 'messenger_messages'];

    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }

        self::bootKernel();
    }

    public function testDoctrineReadsTheDeclarations(): void
    {
        $driver = $this->entityManager()->getConfiguration()->getMetadataDriverImpl();

        self::assertInstanceOf(MappingDriver::class, $driver);
        self::assertInstanceOf(DeclarationDriver::class, $driver->getDriver(), 'the metadata come from the declarations');
    }

    public function testTheDescriptionBuiltWithoutDoctrineIsDoctrines(): void
    {
        $entityManager = $this->entityManager();
        $doctrine = MetadataDescription::of($entityManager);
        // The tables Symfony's listeners add to the schema (Messenger's transport), no entity's.
        $doctrine = new Description($doctrine->entities, array_diff_key($doctrine->tables, array_flip(self::FOREIGN_TABLES)));
        $built = $this->describe();

        self::assertGreaterThan(50, count($built->entities));
        self::assertSame(array_keys($doctrine->entities), array_keys($built->entities), 'the same classes');
        $differences = [];
        foreach ($doctrine->entities as $className => $entity) {
            $differences = array_merge($differences, self::differences($entity->toArray(), $built->entity($className)->toArray(), $className));
        }
        self::assertSame([], $differences, 'the entities, '.count($doctrine->entities).' classes');

        self::assertSame(array_keys($doctrine->tables), array_keys($built->tables), 'the same tables');
        $differences = [];
        foreach ($doctrine->tables as $name => $table) {
            $differences = array_merge($differences, self::differences($table->toArray(), $built->table($name)->toArray(), $name));
        }
        self::assertSame([], $differences, 'the tables, '.count($doctrine->tables));

        // The values of #[DiscriminatorEntry], derived without the attribute's reader.
        $checked = 0;
        foreach ($built->entities as $className => $entity) {
            if (null !== $entity->inheritance && null !== $entity->inheritance->value && [] !== (new \ReflectionClass($className))->getAttributes(\Base\Database\Attribute\DiscriminatorEntry::class)) {
                self::assertSame($entityManager->getClassMetadata($className)->discriminatorValue, DiscriminatorRule::value($className), $className);
                ++$checked;
            }
        }
        self::assertGreaterThan(10, $checked);
    }

    public function testTheDescriptionIsTheDatabasesSchema(): void
    {
        $connection = $this->entityManager()->getConnection();
        if (!$connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            self::markTestSkipped('The database is compared on MySQL ('.$connection->getDatabasePlatform()::class.' here).');
        }

        $pdo = $connection->getNativeConnection();
        self::assertInstanceOf(\PDO::class, $pdo);
        $schema = MySqlSchema::read($pdo, null, self::FOREIGN_TABLES, Describer::mysqlTypes(Describer::types($this->options())));

        self::assertSame([], $schema->differences($this->describe()), 'the description against information_schema');
    }

    private function describe(): Description
    {
        $options = $this->options();

        return Describer::describe($options['paths'], $options);
    }

    /**
     * The entity folders Symfony's attribute drivers map, the project, the targets DoctrineBundle resolves: as HandBuiltEntityManagerTest reads them.
     *
     * @return array<string, mixed>
     */
    private function options(): array
    {
        $entityManager = $this->entityManager();
        $driver = $entityManager->getConfiguration()->getMetadataDriverImpl();
        $driver = $driver instanceof MappingDriver ? $driver->getDriver() : $driver;
        $driver = $driver instanceof DeclarationDriver ? $driver->getDriver() : $driver;
        $paths = [];
        foreach ($driver instanceof MappingDriverChain ? $driver->getDrivers() : [$driver] as $inner) {
            if ($inner instanceof AttributeDriver) {
                $paths = array_merge($paths, $inner->getPaths());
            }
        }

        $targets = [];
        foreach ($entityManager->getEventManager()->getListeners(Events::loadClassMetadata) as $listener) {
            if ($listener instanceof ResolveTargetEntityListener) {
                foreach ((fn () => $this->resolveTargetEntities)->call($listener) as $interface => $mapping) {
                    $targets[$interface] = $mapping['targetEntity'];
                }
            }
        }

        $types = [];
        $container = static::getContainer();
        foreach ($container->hasParameter('doctrine.dbal.connection_factory.types') ? $container->getParameter('doctrine.dbal.connection_factory.types') : [] as $name => $type) {
            if (!str_starts_with($type['class'], 'Symfony\\')) {
                $types[$name] = $type['class'];
            }
        }

        return [
            'paths' => array_values(array_unique($paths)),
            'project_dir' => $container->getParameter('kernel.project_dir'),
            'resolve_target_entities' => $targets,
            'types' => $types,
        ];
    }

    /** @return list<string> */
    private static function differences(mixed $expected, mixed $actual, string $path): array
    {
        if (is_array($expected) && is_array($actual)) {
            if (array_keys($expected) !== array_keys($actual)) {
                return [$path.': keys '.json_encode(array_keys($expected), JSON_UNESCAPED_SLASHES).' / '.json_encode(array_keys($actual), JSON_UNESCAPED_SLASHES)];
            }
            $differences = [];
            foreach ($expected as $key => $value) {
                $differences = array_merge($differences, self::differences($value, $actual[$key], $path.'.'.$key));
            }

            return $differences;
        }

        return $expected === $actual ? [] : [$path.': '.json_encode($expected, JSON_UNESCAPED_SLASHES).' / '.json_encode($actual, JSON_UNESCAPED_SLASHES)];
    }

    private function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get('doctrine.orm.entity_manager');
    }
}
