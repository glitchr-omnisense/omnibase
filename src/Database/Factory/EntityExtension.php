<?php

namespace Base\Database\Factory;

use Base\Database\Factory\EntityExtensionInterface;

class EntityExtension
{
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_REMOVE = 'remove';

    protected $extensions = [];
    public function getExtensions(): array { return $this->extensions; }
    public function addExtension(EntityExtensionInterface $extension): self
    {
        $this->extensions[get_class($extension)] = $extension;
        return $this;
    }

    /**
     * Adds mapping to the translatable and translations.
     */
    public static $entities   = [];
    public static function get() { return self::$entities; }
    public static function has($entity):bool 
    {
        $className = is_object($entity) ? get_class($entity) : $entity;
        return in_array($className, self::$entities); 
    }
}
