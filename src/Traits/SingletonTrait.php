<?php

namespace Base\Traits;

trait SingletonTrait
{
    private static $_instance = null;

    protected function __construct() { }

    public static function hasInstance()
    {
        return !self::$_instance;
    }

    public static function setInstance($instance)
    {
        self::$_instance = $instance;
    }

    public static function getInstance(bool $instanciateIfNotFound = true)
    {
        if ($instanciateIfNotFound && !self::$_instance)
            self::setInstance(new self());

        return self::$_instance;
    }

    protected function __clone() { }
    protected function __sleep() { }
    protected function __wakeup() { }
}