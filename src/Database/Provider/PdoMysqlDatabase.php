<?php 

namespace Base\Database\Provider;

use BackupManager\Databases\MysqlDatabase;

class PdoMysqlDatabase extends MysqlDatabase
{
    public function handles($type)
    {
        return strtolower($type ?? '') == 'pdo_mysql';
    }
}
