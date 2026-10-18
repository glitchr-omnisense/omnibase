<?php

namespace Tests\Base\Bridge\Doctrine;

use Base\Bridge\Doctrine\MySQLSchemaManager;
use Base\Bridge\Doctrine\SchemaManagerFactory;
use Base\Database\Type\ArrayType;
use Base\Database\Type\EnumType;
use Base\Database\Type\MySQLSetType;
use Base\Database\Type\SetType;
use Base\Database\Type\UtcDateTimeImmutableType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\MySQL84Platform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\EnumType as DbalEnumType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\TestCase;

class SchemaManagerTestState extends EnumType
{
    public const DRAFT = 'STATE_DRAFT';
    public const PUBLISH = 'STATE_PUBLISH';
}

class SchemaManagerTestRoles extends SetType
{
    public const ADMIN = 'ROLE_ADMIN';
    public const USER = 'ROLE_USER';
}

/** An application's roles: the core's plus one. */
class SchemaManagerTestAppRoles extends SchemaManagerTestRoles
{
    public const PRO = 'ROLE_PRO';
}

/** Reads a row as MySQL's information_schema gives it, as DBAL's schema manager does when it lists a table. */
final class SchemaManagerTestReader extends MySQLSchemaManager
{
    public function column(array $row): Column
    {
        return $this->_getPortableTableColumnDefinition($row + [
            'default' => null, 'null' => 'NO', 'extra' => '', 'comment' => null,
            // The table's own charset and collation: given as null, as DBAL's MySQL comparator leaves them out.
            'characterset' => null, 'collation' => null,
            'character_maximum_length' => null, 'character_octet_length' => null,
            'numeric_precision' => null, 'numeric_scale' => null,
        ]);
    }
}

/**
 * doctrine/dbal as published, without glitchr/doctrine-dc2type (plan "omnibase
 * agnostique", C6b): a column read back from the database compares equal to
 * its mapping while nothing changed, and differs once an enumeration's values
 * change - without the "(DC2Type:...)" comments the plugin read and
 * EnumSubscriber wrote, which a database of before still has and which are
 * left out. On MySQL (the sites' database: Base\Bridge\Doctrine\MySQLSchemaManager)
 * and on SQLite (the harness's: a schema created, read back and compared).
 */
final class SchemaManagerTest extends TestCase
{
    private static function type(string $class): Type
    {
        $name = strtolower(substr($class, strrpos($class, '\\') + 1));
        if (!Type::hasType($name)) {
            Type::addType($name, $class);
        }

        return Type::getType($name);
    }

    private static function mysql(): Connection
    {
        // Never connected: the server's version given, the platform is known without asking it.
        return DriverManager::getConnection(['driver' => 'pdo_mysql', 'host' => 'nowhere.invalid', 'serverVersion' => '8.4.0']);
    }

    private static function reader(): SchemaManagerTestReader
    {
        return new SchemaManagerTestReader(self::mysql(), new MySQL84Platform());
    }

    public function testOnMySQLTheFactoryGivesTheCoresSchemaManager(): void
    {
        self::assertInstanceOf(MySQLSchemaManager::class, (new SchemaManagerFactory())->createSchemaManager(self::mysql()));
    }

    public function testASetColumnIsReadWithItsValuesAndComparesWithItsMapping(): void
    {
        $platform = new MySQL84Platform();
        $read = self::reader()->column([
            'field' => 'roles', 'type' => 'set', 'column_type' => "set('ROLE_ADMIN','ROLE_USER')",
            'comment' => '(DC2Type:schema_manager_test_roles) ROLE_ADMIN,ROLE_USER',
        ]);

        self::assertInstanceOf(MySQLSetType::class, $read->getType());
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $read->getValues());
        self::assertSame('', $read->getComment(), 'the comment of before is left out, values and all');

        $mapped = new Column('roles', self::type(SchemaManagerTestRoles::class));
        self::assertTrue($platform->columnsEqual($read, $mapped), 'nothing changed: no migration');

        $added = new Column('roles', self::type(SchemaManagerTestAppRoles::class));
        self::assertFalse($platform->columnsEqual($read, $added), 'a value added: the column is altered');
        self::assertStringContainsString("'ROLE_PRO'", $platform->getColumnDeclarationSQL('roles', $added->toArray()));
    }

    public function testAnEnumColumnIsDbalsEnumWithItsValuesAndComparesWithItsMapping(): void
    {
        $platform = new MySQL84Platform();
        $read = self::reader()->column([
            'field' => 'state', 'type' => 'enum', 'column_type' => "enum('STATE_DRAFT','STATE_PUBLISH')",
            'comment' => '(DC2Type:schema_manager_test_state) STATE_DRAFT,STATE_PUBLISH',
        ]);

        self::assertInstanceOf(DbalEnumType::class, $read->getType());
        self::assertSame('', $read->getComment());
        self::assertTrue($platform->columnsEqual($read, new Column('state', self::type(SchemaManagerTestState::class))));

        $old = self::reader()->column(['field' => 'state', 'type' => 'enum', 'column_type' => "enum('STATE_DRAFT')"]);
        self::assertFalse($platform->columnsEqual($old, new Column('state', self::type(SchemaManagerTestState::class))));
    }

    public function testOnlyTheTypeCommentIsLeftOut(): void
    {
        $read = self::reader()->column(['field' => 'data', 'type' => 'longtext', 'column_type' => 'longtext', 'comment' => '(DC2Type:array)']);
        self::assertSame('', $read->getComment());
        self::assertSame(Types::TEXT, Type::lookupName($read->getType()));

        $read = self::reader()->column(['field' => 'bio', 'type' => 'longtext', 'column_type' => 'longtext', 'comment' => 'The author, in a few lines']);
        self::assertSame('The author, in a few lines', $read->getComment());

        $read = self::reader()->column(['field' => 'data', 'type' => 'json', 'column_type' => 'json', 'comment' => '(DC2Type:json) kept as given']);
        self::assertSame('kept as given', $read->getComment());
    }

    public function testOnSQLiteTheCoresColumnsReadBackUnchanged(): void
    {
        if (!Type::hasType(UtcDateTimeImmutableType::NAME)) {
            Type::addType(UtcDateTimeImmutableType::NAME, UtcDateTimeImmutableType::class);
        }
        if (!Type::hasType('omnibase_test_array')) {
            Type::addType('omnibase_test_array', ArrayType::class);
        }

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $table = new Table('schema_manager_test', [
            new Column('id', Type::getType(Types::INTEGER), ['autoincrement' => true]),
            new Column('state', self::type(SchemaManagerTestState::class)),
            new Column('roles', self::type(SchemaManagerTestRoles::class)),
            new Column('attributes', Type::getType('omnibase_test_array')),
            new Column('created_at', Type::getType(UtcDateTimeImmutableType::NAME)),
        ]);
        $table->setPrimaryKey(['id']);

        $schemaManager = (new SchemaManagerFactory())->createSchemaManager($connection);
        $schemaManager->createTable($table);

        $diff = $schemaManager->createComparator()->compareTables($schemaManager->introspectTable('schema_manager_test'), $table);
        $platform = $connection->getDatabasePlatform();
        $changed = array_map(static fn ($change): string => $change->getOldColumn()->getName().': '.$platform->getColumnDeclarationSQL('', $change->getOldColumn()->toArray()).' / '.$platform->getColumnDeclarationSQL('', $change->getNewColumn()->toArray()), $diff->getChangedColumns());
        self::assertTrue($diff->isEmpty(), 'the table read back is the table created: '.implode('; ', $changed));
    }
}
