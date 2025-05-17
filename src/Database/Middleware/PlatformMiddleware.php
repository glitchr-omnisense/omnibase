<?php

namespace Base\Database\Middleware;

use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\ServerVersionProvider;

class PlatformMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new class($driver) extends AbstractDriverMiddleware {

            public function getDatabasePlatform(ServerVersionProvider $connection): AbstractPlatform
            {
                $platform = parent::getDatabasePlatform($connection);

                // Register custom type mappings
                $platform->registerDoctrineTypeMapping('enum', 'string');
                $platform->registerDoctrineTypeMapping('set', 'json');

                return $platform;
            }

            public function connect(array $params): Driver\Connection
            {
                $connection = parent::connect($params);

                return new class($connection) implements Driver\Connection {
                    public function __construct(private Driver\Connection $inner)
                    {
                    }

                    public function prepare(string $sql): Driver\Statement
                    {
                        $this->logQuery($sql);
                        return $this->inner->prepare($sql);
                    }

                    public function query(string $sql): Driver\Result
                    {
                        $this->logQuery($sql);
                        return $this->inner->query($sql);
                    }

                    public function exec(string $sql): int
                    {
                        $this->logQuery($sql);
                        return $this->inner->exec($sql);
                    }

                    public function beginTransaction(): void
                    {
                        $this->inner->beginTransaction();
                    }

                    public function commit(): void
                    {
                        $this->inner->commit();
                    }

                    public function rollBack(): void
                    {
                        $this->inner->rollBack();
                    }

                    public function getServerVersion(): string
                    {
                        return $this->inner->getServerVersion();
                    }

                    public function quote($value, $type = \PDO::PARAM_STR): string
                    {
                        return $this->inner->quote($value, $type);
                    }

                    public function getNativeConnection()
                    {
                        return $this->inner->getNativeConnection();
                    }

                    public function lastInsertId($name = null): string|int
                    {
                        return $this->inner->lastInsertId($name);
                    }

                    private function logQuery(string $sql): void
                    {
                        // $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
                        // foreach ($trace as $frame) {
                        //     if (isset($frame['file'], $frame['line']) && !str_contains($frame['file'], 'vendor/')) {
                        //         $location = $frame['file'] . ':' . $frame['line'];
                        //         break;
                        //     }
                        // }

                        // if(isset($location))
                        //     dump("Query executed from: $location", $sql);
                    }
                };
            }
        };
    }
}