<?php

namespace Base\Database\Entity\Extension;

use Base\Database\Annotation\Alias;
use Doctrine\Common\Collections\Collection;
use ReflectionClass;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\Exception\AccessException;
use Symfony\Component\String\Inflector\EnglishInflector;

trait AliasTrait
{
    protected static array $aliasPropertyMap = [];
    protected static array $aliasPropertyTypes = [];
    protected static ?EnglishInflector $inflector = null;

    protected static function buildAliasMaps(object $object): void
    {
        self::$inflector = self::$inflector ?? new EnglishInflector();
        $ref = new ReflectionClass($object);
        do {

            $class = $ref->getName();
            $ref = new ReflectionClass($class);

            $map = [];
            $types = [];
            foreach ($ref->getProperties() as $property) {

                foreach ($property->getAttributes(Alias::class) as $attr) {

                    $instance = $attr->newInstance();
                    $alias = $instance->alias ?: $property->getName();
                    if($property->class == $class && !isset($map[$alias])) {

                        $map[$instance->column] = $instance->column; // Use column as key for direct property access
                        $map[$alias] = $instance->column; // Use alias as key for alias access (NB: both should react to the same implementations)
                        if ($instance->type !== null) {
                            $types[$alias] = $instance->type; 
                        }              
                    }
                }
            }
            
            $ref = $ref->getParentClass();
            self::$aliasPropertyMap[$class] = $map;
            self::$aliasPropertyTypes[$class] = $types;

        } while ($ref);
    }
    
    public function __call(string $name, array $arguments): mixed
    {
        $class = static::class;
        if (!isset(self::$aliasPropertyMap[$class])) {
            self::buildAliasMaps($this);
        }

        // Support direct property access by alias (e.g., $object->alias())
        $ref = new \ReflectionClass($this);
        do {

            $parentClass = $ref->getName();
            if (isset(self::$aliasPropertyMap[$parentClass][$name])) {
                $property = self::$aliasPropertyMap[$parentClass][$name];
                $propertyAccessor = PropertyAccess::createPropertyAccessorBuilder()->getPropertyAccessor();
                return $propertyAccessor->getValue($this, $property);
            }
            $ref = $ref->getParentClass();

        } while ($ref);

        // Support getter/setter/add/remove/is for aliased properties
        if (preg_match('/^(get|set|add|remove|is)([A-Z][A-Za-z0-9_]*)$/', $name, $matches)) {
            
            $action = $matches[1];

            $alias = lcfirst($matches[2]);
            $map = self::$aliasPropertyMap[$class] ?? [];
            if(!\array_key_exists($alias, $map)) {

                $aliasSingulars = self::$inflector->pluralize($alias);
                foreach ($aliasSingulars as $singular) {
                    if (\array_key_exists($singular, $map)) {
                        $alias = $singular;
                        break;
                    }
                }
            }
                
            if (isset($map[$alias])) {
                
                $property = $map[$alias];
                $propertyAccessor = PropertyAccess::createPropertyAccessorBuilder()->getPropertyAccessor();

                switch ($action) {
                    case 'get':
                        $value = $propertyAccessor->getValue($this, $property);

                        $type = self::$aliasPropertyTypes[$class][$alias] ?? null;
                        if ($type !== null && class_exists($type) && $value instanceof Collection) {
                            return $value->filter(fn($item) => $item instanceof $type);
                        }

                        return $value;

                    case 'is':
                    case 'has':

                        $refProperty = (new \ReflectionClass($this))->getProperty($property);
                        $propType = $refProperty->getType();
                        if ($propType && $propType->getName() !== 'bool') {
                            throw new AccessException("The 'is' accessor is only allowed for boolean properties. Property '{$property}' is not boolean.");
                        }

                        return $propertyAccessor->getValue($this, $property);

                    case 'add':
                    case 'remove':

                        $value = $propertyAccessor->getValue($this, $property);
                        if (!$value instanceof \Doctrine\Common\Collections\Collection) {
                            throw new AccessException("The '{$action}' accessor is only allowed for to-many association properties that are instances of Doctrine\\Common\\Collections\\Collection. Property '{$property}' is not a Collection.");
                        }

                        $type = self::$aliasPropertyTypes[$class][$alias] ?? null;
                        foreach ($arguments as $item) {

                            if ($item === null) {
                                continue;
                            }

                            // Filter before adding/removing: only allow if $item is of correct type
                            if ($type !== null && !($item instanceof $type)) {
                                continue;
                            }

                            if ($action === 'add') {
                                $value->add($item);
                            } elseif ($action === 'remove') {
                                $value->removeElement($item);
                            }
                        }

                        return $this;
                        case 'set':
                            if (count($arguments) !== 1) {
                                throw new AccessException("The 'set' accessor requires exactly one parameter.");
                            }

                            $toSet = $arguments[0];
                            $type = self::$aliasPropertyTypes[$class][$alias] ?? null;

                            // If a type is defined, filter or validate the value
                            if ($type !== null) {
                                if (class_exists($type) && $toSet instanceof Collection) {
                                    // Filter collection items by type
                                    $toSet = $toSet->filter(fn($item) => $item instanceof $type);
                                } elseif (class_exists($type) && $toSet !== null && !($toSet instanceof $type)) {
                                    // If not a collection, ensure correct type or set to null
                                    $toSet = null;
                                }
                            }

                            $propertyAccessor->setValue($this, $property, $toSet);
                            return $this;
                }
            }
        }

        // Compute parent class list for error message
        $parentClasses = [];
        $refClass = new \ReflectionClass($this);
        while ($refClass = $refClass->getParentClass()) {
            $parentClasses[] = $refClass->getName();
        }
        $parentClassList = !empty($parentClasses) ? implode("`, `", $parentClasses) : 'none';
        throw new AccessException(
            sprintf(
                "No alias or aliased property found for method \"%s\" in class \"%s\" or any of its parent classes [%s]. ".
                "Tried to resolve \"%s\" as an alias, property, or aliased getter/setter, but none matched. ".
                "Available aliases: [%s]",
                $name, get_class($this), $parentClassList,
                $name, isset(self::$aliasPropertyMap[$class]) ? implode(', ', array_keys(self::$aliasPropertyMap[$class])) : 'none'
            )
        );
    }
    
    public function __isset(string $alias): bool
    {
        $ref = new \ReflectionClass($this);
        do {
            $class = $ref->getName();

            if (!isset(self::$aliasPropertyMap[$class])) {
                self::buildAliasMaps($this);
            }

            $map = self::$aliasPropertyMap[$class] ?? [];
            if (isset($map[$alias])) {
                $property = $map[$alias];
                $propertyAccessor = PropertyAccess::createPropertyAccessorBuilder()->getPropertyAccessor();
                return $propertyAccessor->isReadable($this, $property) && $propertyAccessor->getValue($this, $property) !== null;
            }

            $ref = $ref->getParentClass();
        } while ($ref);

        return false;
    }
    public function __set(string $alias, mixed $value): void
    {
        $ref = new \ReflectionClass($this);
        do {
            $class = $ref->getName();

            if (!isset(self::$aliasPropertyMap[$class])) {
                self::buildAliasMaps($this);
            }

            $map = self::$aliasPropertyMap[$class] ?? [];
            if (isset($map[$alias])) {
                $property = $map[$alias];
                $propertyAccessor = PropertyAccess::createPropertyAccessorBuilder()->getPropertyAccessor();
                $propertyAccessor->setValue($this, $property, $value);
                return;
            }

            $ref = $ref->getParentClass();
        } while ($ref);

        $class = static::class;
        throw new AccessException("Undefined property: {$class}::\${$alias}");
    }

    public function __get(string $alias): mixed
    {
        $ref = new \ReflectionClass($this);
        do {
            $class = $ref->getName();

            if (!isset(self::$aliasPropertyMap[$class])) {
                self::buildAliasMaps($this);
            }

            $map = self::$aliasPropertyMap[$class] ?? [];
            if (isset($map[$alias])) {
                $property = $map[$alias];
                return $this->{$property};
            }

            $ref = $ref->getParentClass();
        } while ($ref);

        $class = static::class;
        throw new AccessException("Undefined property: {$class}::\${$alias}");
    }

}