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

    public static function getInstance(bool $instanciateIfNotFound = true): ?self
    {
        if ($instanciateIfNotFound && !self::$_instance)
            self::setInstance(new self());

        return self::$_instance;
    }

    public function __clone()  { throw new \Exception("\"". get_called_class(). "\" follows a singleton pattern. This method is protected"); }
    public function __sleep()  { throw new \Exception("\"". get_called_class(). "\" follows a singleton pattern. This method is protected"); }
    public function __wakeup() { throw new \Exception("\"". get_called_class(). "\" follows a singleton pattern. This method is protected"); }
}
