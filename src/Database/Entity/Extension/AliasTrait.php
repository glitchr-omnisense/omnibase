<?php

namespace Base\Database\Entity\Extension;

use Base\Database\Annotation\Alias;
use Doctrine\Common\Collections\Collection;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\Exception\AccessException;
use Symfony\Component\String\Inflector\EnglishInflector;

trait AliasTrait
{
    private static array $aliasPropertyMap = [];
    private static ?EnglishInflector $inflector = null;

    /** Build alias map once per class */
    private static function buildAliasMaps(object $obj): void
    {
        $class = new ReflectionClass($obj);
        $map = [];
        foreach ($class->getProperties() as $prop) {
            foreach ($prop->getAttributes(Alias::class) as $attr) {
                /** @var Alias $alias */
                $alias = $attr->newInstance();
                $map[$alias->alias] = $alias->column;
            }
        }
        self::$aliasPropertyMap[$class->getName()] = $map;
    }

    /** Reflection property fetch (safe, no __get) */
    private function getPropertyRef(string $property): ?ReflectionProperty
    {
        $rc = new ReflectionClass($this);
        if ($rc->hasProperty($property)) {
            $rp = $rc->getProperty($property);
            $rp->setAccessible(true);
            return $rp;
        }
        return null;
    }

    /** Convert any iterable to array */
    private function toArray(mixed $value): array
    {
        if ($value instanceof Collection) return $value->toArray();
        if (is_array($value)) return $value;
        if ($value instanceof \Traversable) return iterator_to_array($value, false);
        if ($value === null) return [];
        return [$value];
    }

    private function resolveAlias(string $name): string
    {
        $class = static::class;

        // build cache if needed
        if (!isset(self::$aliasPropertyMap[$class])) {
            self::buildAliasMaps($this);
        }

        $map = self::$aliasPropertyMap[$class] ?? [];

        // If we have an explicit alias mapping, return the real column name
        if (isset($map[$name])) {
            return $map[$name];
        }

        // No alias mapping, just return original
        return $name;
    }

    /** ------------------- Magic Accessors ------------------- */
    public function __get(string $name): mixed
    {
        $real = $this->resolveAlias($name);
        if ($rp = $this->getPropertyRef($real)) {
            return $rp->getValue($this);
        }
        
        throw new AccessException(
            sprintf('Undefined alias property access: %s::$%s', static::class, $name)
        );
    }

    public function __set(string $name, mixed $value): void
    {
        $real = $this->resolveAlias($name);
        if ($rp = $this->getPropertyRef($real)) {
            $rp->setValue($this, $value);
            return;
        }
    
        throw new AccessException(
            sprintf('Cannot set undefined alias property: %s::$%s', static::class, $name)
        );
    }

    public function __isset(string $name): bool
    {
        $real = $this->resolveAlias($name);
        if ($rp = $this->getPropertyRef($real)) {
            $val = $rp->getValue($this);
            return $val !== null;
        }

        return false;
    }

    public function __call(string $name, array $arguments): mixed
    {
        $class = static::class;
        if (!isset(self::$aliasPropertyMap[$class])) {
            self::buildAliasMaps($this);
        }
        $map = self::$aliasPropertyMap[$class] ?? [];

        if (!preg_match('/^(get|set|add|remove|is|has)([A-Z][A-Za-z0-9_]*)$/', $name, $m)) {
            throw new AccessException("Undefined alias method: {$name} in {$class}");
        }

        $action     = $m[1];
        $aliasToken = lcfirst($m[2]);
        $property   = $map[$aliasToken] ?? $aliasToken;

        $uc = ucfirst($property);

        switch ($action) {
            case 'get':
            case 'set':
            case 'is':
            case 'has':
                $method = $action . $uc;
                if (method_exists($this, $method)) {
                    return $this->{$method}(...$arguments);
                }
                if ($rp = $this->getPropertyRef($property)) {
                    if ($action === 'get') {
                        return $rp->getValue($this);
                    }
                    if ($action === 'set') {
                        $rp->setValue($this, $arguments[0] ?? null);
                        return $this;
                    }
                    if (in_array($action, ['is','has'], true)) {
                        $val = $rp->getValue($this);
                        if ($val instanceof Collection) return $val->count() > 0;
                        if (is_array($val)) return !empty($val);
                        return (bool) $val;
                    }
                }
                throw new AccessException("Cannot resolve {$action} for alias '{$aliasToken}' → '{$property}'");

            case 'add':
            case 'remove':
                if (self::$inflector === null) {
                    self::$inflector = new EnglishInflector();
                }
                $singulars = self::$inflector->singularize($property);
                if (empty($singulars)) {
                    $singulars = [$property];
                }
                $methodFound = false;
                $items = $arguments[0] ?? null;
                foreach ($singulars as $singCap) {
                    $method = $action . ucfirst($singCap);
                    if (method_exists($this, $method)) {
                        foreach ($this->toArray($items) as $item) {
                            $this->{$method}($item);
                        }
                        $methodFound = true;
                        break;
                    }
                }
                if (!$methodFound) {
                    throw new AccessException("No suitable {$action} method found for alias '{$aliasToken}'");
                }
                return $this;
        }

        throw new AccessException("Unsupported alias action in {$name}");
    }
}