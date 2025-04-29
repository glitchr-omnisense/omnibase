<?php

namespace Base\Database\Middleware;

use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver;

class PlatformMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        $platform = $driver->getDatabasePlatform();
        $platform->registerDoctrineTypeMapping('enum', 'string');
        $platform->registerDoctrineTypeMapping('set', 'json');
        return $driver;
    }
}