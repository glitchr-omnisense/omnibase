<?php

namespace Tests\Base\Bridge\Doctrine;

use Base\Bridge\Doctrine\MappingDriver;
use Base\Database\Schema\Describer;
use Base\Database\Schema\DiscriminatorRule;
use Base\Database\Schema\EntitySchema;
use Base\Database\Schema\TranslationRule;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\ResolveTargetEntityListener;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Omnibridge\Doctrine\DeclarationDriver;
use Omnibridge\Doctrine\MetadataDescription;
use Omnibridge\Doctrine\Omnibase\Database\Walker\TranslatableWalker;
use Omnibridge\Schema\Column;
use Omnibridge\Schema\Database\MySqlSchema;
use Omnibridge\Schema\Database\MySqlStatements;
use Omnibridge\Schema\Database\SchemaCreator;
use Omnibridge\Schema\Database\SqliteSchema;
use Omnibridge\Schema\Database\SqliteStatements;
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
 *   and foreign key read from information_schema, nothing of Doctrine asked;
 * - the core builds the same description with no persistence installed
 *   (Base\Database\Schema\EntitySchema, plan "omnibase agnostic", step F7a),
 *   its column types declare what Doctrine's types declare, and the database
 *   omnisense/omnibridge's SchemaCreator makes from it is the one Doctrine's
 *   SchemaTool makes from the mapping: on SQLite (the harness) in memory, on
 *   MySQL in the two empty databases OMNIBASE_SCHEMA_DATABASES names ("a,b",
 *   on the application's server; skipped without), emptied again afterwards.
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

    /** The core's description, built with no persistence (EntitySchema), is the Doctrine bridge's (Describer). */
    public function testTheCoreDescribesTheEntitiesAsTheDoctrineBridgeDoes(): void
    {
        $options = $this->options();

        self::assertSame(Describer::describe($options['paths'], $options)->toArray(), EntitySchema::describe($options['paths'], $options)->toArray());
        self::assertSame([TranslatableWalker::LOCALE, TranslatableWalker::FOREIGN_KEY, TranslatableWalker::COLUMN_NAME, TranslatableWalker::SALT], [TranslationRule::LOCALE, TranslationRule::FOREIGN_KEY, TranslationRule::COLUMN_NAME, TranslationRule::SALT], 'the translations\' names the walker queries');
    }

    /** omnibase's column types as data (EntitySchema::columnTypes()) are written as Doctrine's types declare them, on MySQL and on SQLite. */
    public function testTheColumnTypesDeclareWhatDoctrinesTypesDeclare(): void
    {
        $options = $this->options();
        $types = EntitySchema::columnTypes($options);
        $mysql = new MySQL80Platform();
        $sqlite = new SQLitePlatform();

        foreach (EntitySchema::ALIASES as $name => $portable) {
            foreach ([null, 36, 255, 70000] as $length) {
                $column = $types->resolve(new Column('c', $name, length: $length));
                self::assertSame(strtolower(Type::getType($name)->getSQLDeclaration(['length' => $length], $mysql)), (new MySqlStatements($types))->sqlType($column), $name.'('.$length.') on MySQL');
                self::assertSame(Type::getType($name)->getSQLDeclaration(['length' => $length], $sqlite), (new SqliteStatements($types))->sqlType($column), $name.'('.$length.') on SQLite');
            }
        }

        $doctrine = Describer::mysqlTypes(Describer::types($options));
        $ours = $types->mysql();
        $checked = 0;
        foreach (array_keys($types->enumerations + $types->sets) as $name) {
            self::assertSame($doctrine[$name](new Column('c', $name)), $ours[$name](new Column('c', $name)), $name);
            self::assertSame('CLOB', Type::getType($name)->getSQLDeclaration([], $sqlite), $name.' on SQLite');
            ++$checked;
        }
        self::assertGreaterThan(10, $checked);
    }

    /**
     * The database made from the description (SchemaCreator, no ORM) is the one Doctrine's SchemaTool makes from the
     * mapping: every table, column (type, nullable, default, auto-increment, in order), primary key, index and foreign
     * key read back from both, nothing differs.
     */
    public function testTheSchemaCreatedFromTheDescriptionIsSchemaToolsOne(): void
    {
        $entityManager = $this->entityManager();
        $connection = $entityManager->getConnection();
        $options = $this->options();
        $types = EntitySchema::columnTypes($options);
        $description = EntitySchema::describe($options['paths'], $options);
        $doctrine = (new SchemaTool($entityManager))->getCreateSchemaSql($entityManager->getMetadataFactory()->getAllMetadata());

        if ($connection->getDatabasePlatform() instanceof SQLitePlatform) {
            [$tool, $created] = [new \PDO('sqlite::memory:'), new \PDO('sqlite::memory:')];
            foreach ($doctrine as $statement) {
                $tool->exec($statement);
            }
            (new SchemaCreator($created, $types))->create($description);

            self::assertSame([], SqliteSchema::read($tool, self::FOREIGN_TABLES)->against(SqliteSchema::read($created, self::FOREIGN_TABLES)), 'SchemaTool\'s database against the description\'s');
            self::assertSame([], SqliteSchema::read($created, [], $types)->differences($description));
            self::assertGreaterThan(50, \count($description->tables));

            return;
        }

        $databases = array_values(array_filter(explode(',', (string) getenv('OMNIBASE_SCHEMA_DATABASES'))));
        if (!$connection->getDatabasePlatform() instanceof AbstractMySQLPlatform || 2 !== \count($databases)) {
            self::markTestSkipped('On MySQL, OMNIBASE_SCHEMA_DATABASES names two empty databases of the application\'s server ("a,b") the comparison creates its tables in.');
        }

        $parameters = $connection->getParams();
        $pdo = static fn (string $database) => new \PDO('mysql:host='.$parameters['host'].';port='.($parameters['port'] ?? 3306).';dbname='.$database.';charset=utf8mb4', $parameters['user'] ?? null, $parameters['password'] ?? null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        [$tool, $created] = [$pdo($databases[0]), $pdo($databases[1])];
        $tableOptions = $parameters['defaultTableOptions'] ?? [];
        foreach ([$tool, $created] as $i => $database) {
            if ([] !== $database->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN)) {
                self::fail($databases[$i].' holds tables: the comparison runs on two empty databases (nothing was changed).');
            }
        }
        try {
            foreach ($doctrine as $statement) {
                $tool->exec($statement);
            }
            (new SchemaCreator($created, $types, ['charset' => $tableOptions['charset'] ?? 'utf8mb4', 'collation' => $tableOptions['collate'] ?? $tableOptions['collation'] ?? null]))->create($description);

            self::assertSame([], MySqlSchema::read($tool, null, self::FOREIGN_TABLES)->against(MySqlSchema::read($created, null, self::FOREIGN_TABLES)), 'SchemaTool\'s database against the description\'s');
            self::assertSame([], MySqlSchema::read($created, null, [], $types->mysql())->differences($description));
        } finally {
            foreach ([$tool, $created] as $database) {
                $database->exec('SET FOREIGN_KEY_CHECKS = 0');
                foreach ($database->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN) as $table) {
                    $database->exec('DROP TABLE `'.$table.'`');
                }
                $database->exec('SET FOREIGN_KEY_CHECKS = 1');
            }
        }
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
        // The enumerations DBAL has, a bundle's among them (omnibase/marketplace's order_state), as omnibridge/symfony's
        // omnibridge:schema:create reads them.
        foreach (Type::getTypeRegistry()->getMap() as $name => $type) {
            if (method_exists($type, 'enumeration')) {
                $types[$name] ??= $type->enumeration();
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
