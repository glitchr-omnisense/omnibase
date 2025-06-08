<?php

namespace Base\Database\Entity\Extension;

use Base\Database\Annotation\Alias;
use ReflectionClass;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\Exception\AccessException;

trait AliasTrait
{
    private static array $aliasPropertyMap = [];

    public function __get(string $alias): mixed
    {
        $class = static::class;

        if (!isset(self::$aliasPropertyMap[$class])) {
            self::buildAliasMaps($this);
        }

        $map = self::$aliasPropertyMap[$class];
        if (isset($map[$alias])) {
            $property = $map[$alias];
            return $this->{$property};
        }

        throw new AccessException("Undefined property: {$class}::\${$alias}");
    }

    public function __isset(string $alias): bool
    {
        $class = static::class;

        if (!isset(self::$aliasPropertyMap[$class])) {
            self::buildAliasMaps($this);
        }

        $map = self::$aliasPropertyMap[$class];
        if (isset($map[$alias])) {
            $property = $map[$alias];
            $propertyAccessor = PropertyAccess::createPropertyAccessorBuilder()->getPropertyAccessor();
            return $propertyAccessor->isReadable($this, $property) && $propertyAccessor->getValue($this, $property) !== null;
        }

        return false;
    }

    public function __set(string $alias, mixed $value): void
    {
        $class = static::class;

        if (!isset(self::$aliasPropertyMap[$class])) {
            self::buildAliasMaps($this);
        }

        $map = self::$aliasPropertyMap[$class];
        if (isset($map[$alias])) {
            $property = $map[$alias];
            $propertyAccessor = PropertyAccess::createPropertyAccessorBuilder()->getPropertyAccessor();
            $propertyAccessor->setValue($this, $property, $value);
            return;
        }

        throw new AccessException("Undefined property: {$class}::\${$alias}");
    }

    private static function buildAliasMaps(object $object): void
    {
        $class = static::class;
        $map = [];
        $ref = new ReflectionClass($object);

        foreach ($ref->getProperties() as $property) {
            foreach ($property->getAttributes(Alias::class) as $attr) {
                /** @var Alias $instance */
                $instance = $attr->newInstance();
                $alias = $instance->alias ?? $property->getName();
                $map[$alias] = $property->getName();
            }
        }

        self::$aliasPropertyMap[$class] = $map;
    }
}