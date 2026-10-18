<?php

namespace Base\Bridge\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Schema\SchemaManagerFactory as SchemaManagerFactoryInterface;

/**
 * The schema managers of an omnibase connection (plan "omnibase agnostique",
 * C6b): on MySQL and MariaDB, Base\Bridge\Doctrine\MySQLSchemaManager, which
 * reads a SET column with its values and leaves out the "(DC2Type:...)"
 * comments of before; on any other platform, the one the factory it wraps
 * gives (DBAL's DefaultSchemaManagerFactory unless another is given).
 *
 * doctrine/dbal as published, no patch: DBAL's own SchemaManagerFactory
 * extension point. Symfony: the core decorates DoctrineBundle's
 * doctrine.dbal.default_schema_manager_factory (config/services/media.php).
 * By hand: $configuration->setSchemaManagerFactory(new SchemaManagerFactory())
 * on the DBAL Configuration given to DriverManager::getConnection().
 */
final class SchemaManagerFactory implements SchemaManagerFactoryInterface
{
    public function __construct(private readonly ?SchemaManagerFactoryInterface $inner = null)
    {
    }

    public function createSchemaManager(Connection $connection): AbstractSchemaManager
    {
        $platform = $connection->getDatabasePlatform();
        if ($platform instanceof AbstractMySQLPlatform) {
            return new MySQLSchemaManager($connection, $platform);
        }

        return ($this->inner ?? new DefaultSchemaManagerFactory())->createSchemaManager($connection);
    }
}
