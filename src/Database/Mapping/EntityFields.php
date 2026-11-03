<?php

namespace Base\Database\Mapping;

use Base\Database\Attribute\Alias;

/**
 * An entity's properties by the names the core's code gives them, on no ORM (plan "omnibase agnostic",
 * step F5c, item 3): its fields and associations from its shape, and its aliases - a property bound to
 * another by #[Alias] (Gallery's "galleries" to "children") -, read from the attributes. What
 * ClassMetadataManipulator's getFieldNames(), getFieldName(), resolveFieldPath() and fetchEntityName()
 * answered from Doctrine's metadata and its completor (the aliases Alias::loadClassMetadata() wrote
 * there), for EntityHydrator.
 *
 * The aliases are read as omnibase's attribute reader gives them to the mapping: the class's own
 * #[Alias(column:, alias:)] attributes, and #[Alias(column:)] on each of its properties - its own and
 * the inherited ones that are not private -, the property being the alias.
 */
final class EntityFields
{
    /** @var array<string, array<string, string>> class => alias => property */
    private static array $aliases = [];

    public function __construct(private readonly EntityShapes $shapes)
    {
    }

    public function shapes(): EntityShapes
    {
        return $this->shapes;
    }

    /**
     * The aliases of a class, alias => the property it is bound to.
     *
     * @return array<string, string>
     */
    public static function aliases(string|object $class): array
    {
        $class = \is_object($class) ? $class::class : ltrim($class, '\\');
        if (isset(self::$aliases[$class])) {
            return self::$aliases[$class];
        }
        if (!class_exists($class)) {
            return [];
        }

        $aliases = [];
        $reflection = new \ReflectionClass($class);
        foreach ($reflection->getAttributes(Alias::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            $alias = $attribute->newInstance();
            if ('' !== $alias->alias) {
                $aliases[$alias->alias] = $alias->column;
            }
        }
        foreach ($reflection->getProperties() as $property) {
            foreach ($property->getAttributes(Alias::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                $aliases[$property->getName()] = $attribute->newInstance()->column;
            }
        }

        return self::$aliases[$class] = $aliases;
    }

    /**
     * Every name of the class's properties, name => property: its aliases, its fields by their columns
     * and its associations (ClassMetadataManipulator::getFieldNames(), Doctrine's fieldNames being
     * column => field).
     *
     * @return array<string, string>
     */
    public function names(string|object $class): array
    {
        $shape = $this->shapes->shape($class);
        $columns = [];
        foreach ($shape->entity->fields as $property => $field) {
            $columns[$field->column] = $property;
        }
        $associations = $shape->associationNames();

        return array_merge(
            self::aliases($shape->className()),
            $columns,
            array_combine($associations, $associations)
        );
    }

    /** The property a name designates (an alias resolved), null for none. */
    public function name(string|object $class, string $name): ?string
    {
        return $this->names($class)[$name] ?? null;
    }

    /** Whether the name (an alias of one included) is one of the class's associations. */
    public function hasAssociation(string|object $class, ?string $name): bool
    {
        if (null === $name || !$this->mapped($class)) {
            return false;
        }

        return $this->shapes->shape($class)->hasAssociation($this->name($class, $name) ?? $name);
    }

    /**
     * A path of names resolved to the properties it goes through ("parent.slug"), each alias replaced,
     * a name the class lacks looked for in its parents; null when it leads nowhere
     * (ClassMetadataManipulator::resolveFieldPath()).
     */
    public function resolve(string|object $class, array|string $path): ?string
    {
        if (!$this->mapped($class)) {
            return null;
        }

        $className = $this->shapes->shape($class)->className();
        $path = \is_array($path) ? $path : explode('.', $path);
        $name = begin($path);

        $declarer = $className;
        $names = $this->names($declarer);
        while (!\array_key_exists($name, $names)) {
            $parent = get_parent_class($declarer);
            if (!$parent || !$this->mapped($parent)) {
                break;
            }
            $declarer = $parent;
            $names = $this->names($declarer);
        }

        $name = $names[$name] ?? $name;
        if (!$name) {
            return null;
        }

        $shape = $this->shapes->shape($className);
        $path = tail($path, $shape->isToMany($name) ? -2 : -1);
        if (!$path) {
            return $name;
        }

        $rest = $shape->hasAssociation($name) ? $this->resolve($shape->targetClass($name), $path) : null;
        if (!$rest) {
            return null;
        }

        return $name.'.'.$rest;
    }

    /** The class an association path leads to (ClassMetadataManipulator::fetchEntityName()), null for a field or nothing. */
    public function target(string $class, array|string $path): ?string
    {
        $path = \is_array($path) ? $path : explode('.', $path);
        $shape = $this->shapes->shape($class);
        $name = $this->name($class, begin($path)) ?? begin($path);

        if (!$shape->hasAssociation($name)) {
            return null;
        }

        $first = $path ? begin($path) : $name;
        $path = tail($path, $shape->isToMany($this->name($class, $first) ?? $first) ? -2 : -1);
        if (!$path) {
            return $shape->targetClass($name);
        }

        return $this->target($shape->targetClass($name), implode('.', $path));
    }

    /** Whether the class has a shape (an entity, a mapped superclass, an embeddable). */
    public function mapped(string|object $class): bool
    {
        if (\is_string($class) && !class_exists($class)) {
            return false;
        }
        if ($this->shapes->has($class)) {
            return true;
        }
        try {
            $this->shapes->shape($class);

            return true;
        } catch (\OutOfBoundsException) {
            return false;
        }
    }
}
