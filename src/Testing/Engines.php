<?php

namespace Base\Testing;

use Base\Database\Repository\Finders;
use Base\Database\Schema\EntitySchema;
use Base\Service\Model\ClassAliases;
use Base\Service\Model\ClassFinder;
use Omnibridge\Schema\Builder;
use Omnibridge\Schema\Database\SchemaCreator;
use Omnibridge\Schema\Description;

/**
 * The same repositories on BOTH engines over the same rows, for a bundle's or a site's tests: one in-memory SQLite
 * database (a fresh MySQL one when ENGINES_MYSQL_HOST is set) whose tables are made from omnibase's description (EntitySchema: the core's entities, the folders of the
 * classes given, omnibase's rules), Doctrine's Finders and Eloquent's Finders over it - as a site has them.
 *
 *     final class PostStatementsTest extends TestCase
 *     {
 *         private static Engines $engines;
 *
 *         public static function setUpBeforeClass(): void
 *         {
 *             self::$engines = Engines::of([Post::class, Topic::class]);
 *             self::$engines->seed(new Topic('First'));                       // written once, through Doctrine
 *         }
 *
 *         public static function backends(): array { return Engines::backends(); }
 *
 *         #[DataProvider('backends')]
 *         public function testTheLatest(string $backend): void
 *         {
 *             $posts = new PostRepository(self::$engines->finders($backend));
 *             self::assertSame(['First'], array_map(fn ($t) => $t->getTitle(), $posts->latest()));
 *         }
 *     }
 *
 * Rows can be written by hand too (insert(), with discriminator() for the class column: the same value on both
 * engines). Needs omnibridge/doctrine and omnibridge/eloquent installed (a bundle's require-dev).
 *
 * Options: EntitySchema's (project_dir, enumerations, types, resolve_target_entities...), and `parameters` for
 * omnibase's Doctrine mapping (base.uploader.storage is given a default).
 */
final class Engines
{
    public const BACKENDS = ['doctrine', 'eloquent'];

    /** @param array<string, Finders> $finders */
    private function __construct(
        private readonly \PDO $pdo,
        private readonly Description $description,
        private readonly object $entityManager,
        private readonly object $store,
        private readonly array $finders,
    ) {
    }

    /**
     * @param list<class-string>   $classes the entities the test reads and writes (their folders are described with the core's)
     * @param array<string, mixed> $options EntitySchema's options, `parameters` for Doctrine's mapping
     */
    public static function of(array $classes, array $options = []): self
    {
        if (!class_exists(\Omnibridge\Doctrine\EntityManagers::class) || !class_exists(\Omnibridge\Eloquent\EntityStore::class)) {
            throw new \LogicException('Base\Testing\Engines needs omnibridge/doctrine and omnibridge/eloquent (require-dev).');
        }
        $options += ['project_dir' => sys_get_temp_dir().'/omnibase-no-project'];
        // App\Entity\X is Base\Entity\X where the application has none of its own: associations name them.
        ClassAliases::register();

        $folders = array_values(array_unique([EntitySchema::coreDirectory('Entity'), ...array_map(
            static fn (string $class): string => \dirname((string) (new \ReflectionClass($class))->getFileName()),
            $classes,
        )]));
        // An application's own App\Entity\X over the core's Base\Entity\X (a host's, the harness's demo): its folder too.
        foreach (ClassFinder::classes(EntitySchema::coreDirectory('Entity')) as $core) {
            $app = 'App\\'.substr($core, \strlen('Base\\'));
            if (str_starts_with($core, 'Base\\Entity\\') && class_exists($app) && str_starts_with((new \ReflectionClass($app))->getName(), 'App\\')) {
                $folders[] = \dirname((string) (new \ReflectionClass($app))->getFileName());
            }
        }
        $folders = array_values(array_unique($folders));
        $described = [];
        foreach ($folders as $folder) {
            $described = [...$described, ...ClassFinder::classes($folder)];
        }
        // omnibase's rules (discriminator entries, order columns, vaults, translations, targets): the description every host reads.
        $description = (new Builder(rules: EntitySchema::rules($options)))->build(array_values(array_unique([...$described, ...$classes])));
        $types = EntitySchema::columnTypes($options);

        $connection = self::connection();
        $pdo = $connection->getNativeConnection();
        \assert($pdo instanceof \PDO);
        (new SchemaCreator($pdo, $types))->create($description);

        $entityManager = \Omnibridge\Doctrine\EntityManagers::create($connection, $folders, configure: \Omnibridge\Doctrine\Omnibase\Mapping::configure(...));
        \Omnibridge\Doctrine\Omnibase\Mapping::register($entityManager, ['parameters' => ($options['parameters'] ?? []) + ['base.uploader.storage' => 'local.uploads']] + $options);
        $store = new \Omnibridge\Eloquent\EntityStore(\Omnibridge\Eloquent\Connections::pdo($pdo), new \Omnibridge\Eloquent\Mapping($description), \Omnibridge\Eloquent\Casts::utc($types));

        return new self($pdo, $description, $entityManager, $store, [
            'doctrine' => \Omnibridge\Doctrine\Omnibase\DoctrineFinders::over($entityManager),
            'eloquent' => new \Omnibridge\Eloquent\Omnibase\EloquentFinders($store, new \Omnibridge\Eloquent\EloquentUnitOfWork($store)),
        ]);
    }

    /**
     * SQLite in memory; or, when ENGINES_MYSQL_HOST is set (with ENGINES_MYSQL_PASSWORD, user root), a MySQL database
     * `engines` made afresh: the same tests on MySQL (omnibase/core _tools: run-mysql).
     */
    private static function connection(): \Doctrine\DBAL\Connection
    {
        $host = getenv('ENGINES_MYSQL_HOST');
        if (false === $host || '' === $host) {
            return \Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        }
        $server = ['driver' => 'pdo_mysql', 'host' => $host, 'user' => 'root', 'password' => (string) getenv('ENGINES_MYSQL_PASSWORD'), 'charset' => 'utf8mb4'];
        $admin = \Doctrine\DBAL\DriverManager::getConnection($server);
        $admin->executeStatement('DROP DATABASE IF EXISTS engines');
        $admin->executeStatement('CREATE DATABASE engines');
        $admin->close();

        $connection = \Doctrine\DBAL\DriverManager::getConnection($server + ['dbname' => 'engines']);
        // As SQLite's in-memory database: no foreign key checked (a test seeds the rows it reads, not their parents).
        $connection->executeStatement('SET SESSION foreign_key_checks = 0');

        return $connection;
    }

    /** @return array<string, array{0: string}> a data provider: each backend's name */
    public static function backends(): array
    {
        return array_combine(self::BACKENDS, array_map(static fn (string $backend): array => [$backend], self::BACKENDS));
    }

    /** The engine of a backend ('doctrine' or 'eloquent'), what was loaded before forgotten: a repository is built from it. */
    public function finders(string $backend): Finders
    {
        $finders = $this->finders[$backend] ?? throw new \InvalidArgumentException(sprintf('No backend "%s": %s.', $backend, implode(', ', self::BACKENDS)));
        $this->clear();

        return $finders;
    }

    /** Entities written through Doctrine's unit of work, then forgotten: the rows both engines read. */
    public function seed(object ...$entities): void
    {
        $unitOfWork = $this->finders['doctrine']->unitOfWork();
        foreach ($entities as $entity) {
            $unitOfWork->persist($entity);
        }
        $unitOfWork->flush();
        $this->clear();
    }

    /**
     * A row written by hand in a table; its identifier.
     *
     * @param array<string, mixed> $values column => value
     */
    public function insert(string $table, array $values): int
    {
        $quote = 'mysql' === $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) ? static fn (string $name): string => '`'.$name.'`' : static fn (string $name): string => '"'.$name.'"';
        $this->pdo->prepare(sprintf('INSERT INTO %s (%s) VALUES (%s)', $quote($table), implode(', ', array_map($quote, array_keys($values))), implode(', ', array_fill(0, \count($values), '?'))))
            ->execute(array_map(static fn (mixed $value): mixed => \is_bool($value) ? (int) $value : $value, array_values($values)));

        return (int) $this->pdo->lastInsertId();
    }

    /** The value a row of that class holds in its discriminator column (the same on both engines). */
    public function discriminator(string $class): string
    {
        return (string) ($this->description->entity($class)?->inheritance?->value ?? throw new \InvalidArgumentException(sprintf('%s has no discriminator.', $class)));
    }

    /** The database, to read what a statement wrote. */
    public function pdo(): \PDO
    {
        return $this->pdo;
    }

    /** The description the tables and Eloquent's mapping are made from. */
    public function description(): Description
    {
        return $this->description;
    }

    private function clear(): void
    {
        $this->entityManager->clear();
        $this->store->clear();
    }
}
