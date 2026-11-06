<?php

namespace Tests\Base\Console;

use Base\Database\Schema\EntitySchema;
use Omnibridge\Schema\Database\SchemaCreator;
use Omnibridge\Symfony\Omnibase\Console\InstalledEntities;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * omnibridge:schema:create under Symfony (plan "omnibase agnostic", step F7a; omnibridge/symfony's adapter): the
 * statements it prints are SchemaCreator's for the installed entities' description on the application's connection,
 * and on a database that already has the tables (the harness's, an application's test database) it refuses and
 * runs nothing. Creating them in an empty database is SchemaDescriptionTest's (SQLite, the scratch MySQL databases).
 */
final class SchemaCreateCommandTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }
        self::bootKernel();
    }

    private static function tester(): CommandTester
    {
        return new CommandTester((new Application(static::$kernel))->find('omnibridge:schema:create'));
    }

    public function testTheStatementsPrintedAreThoseOfTheInstalledEntities(): void
    {
        $tester = self::tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['--dump-sql' => true]));
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $options = InstalledEntities::options($entityManager, static::getContainer()->getParameter('kernel.project_dir'));
        $connection = $entityManager->getConnection();
        $tableOptions = $connection->getParams()['defaultTableOptions'] ?? [];
        $statements = (new SchemaCreator($connection->getNativeConnection(), EntitySchema::columnTypes($options), ['charset' => $tableOptions['charset'] ?? 'utf8mb4', 'collation' => $tableOptions['collate'] ?? $tableOptions['collation'] ?? null]))
            ->statements(EntitySchema::describe($options['paths'], $options));

        self::assertGreaterThan(100, \count($statements));
        self::assertSame(implode('', array_map(static fn (string $statement) => $statement.";\n", $statements)), $tester->getDisplay(true));
        self::assertContains(EntitySchema::coreDirectory('Entity'), array_map('realpath', $options['paths']), 'the core\'s entities among the folders Doctrine maps');
    }

    public function testADatabaseHoldingTheTablesIsRefusedNothingRun(): void
    {
        $tester = self::tester();

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('already holds', $tester->getDisplay(true));
        self::assertStringContainsString('omnibridge:schema:create creates a schema in an empty database', $tester->getDisplay(true));
    }
}
