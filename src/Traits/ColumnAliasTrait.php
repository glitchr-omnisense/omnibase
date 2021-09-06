<?php

namespace Base\Traits;

use Doctrine\ORM\Mapping\ClassMetadata;

trait ColumnAliasTrait 
{
    protected static $columnAlias = [];
    protected static $columnAliasStr = [];
    protected static $columnAliasClass = null;

    public static function hasColumn(string $column)
    {
        return isset(self::$$column);
    }

    public static function getColumnAlias($alias = null)
    {
        return self::$columnAlias;
    }

    public static function getColumnAliasSingularStr($alias)
    {
        return self::$columnAliasStr[$alias][0] ?? null;
    }

    public static function getColumnAliasPluralStr($alias)
    {
        return self::$columnAliasStr[$alias][1] ?? null;
    }

    public static function getColumnAliasClass() {
        return self::$columnAliasClass;
    }
}
