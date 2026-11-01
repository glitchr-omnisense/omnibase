<?php

namespace Base\Database\Mapping;

use Omnibridge\Schema\Description;
use Omnibridge\Schema\Entity;

/**
 * The entities' shapes read from a Description (plan "omnibase agnostic", step F2c): Base\Database\Schema\
 * Describer's (built from the entities' folders without any ORM), or a JSON file's. Mapped superclasses
 * and embeddables have a shape, and are no entities for has().
 */
final class DescriptionShapes implements EntityShapes
{
    /** @var array<string, EntityShape> */
    private array $shapes = [];

    public function __construct(private readonly Description $description)
    {
    }

    public function has(string|object $class): bool
    {
        $entity = $this->description->entities[$this->name($class)] ?? null;

        return null !== $entity && Entity::ENTITY === $entity->kind;
    }

    public function shape(string|object $class): EntityShape
    {
        $name = $this->name($class);
        if (!isset($this->description->entities[$name])) {
            throw new \OutOfBoundsException(sprintf('No class %s in the description.', $name));
        }

        return $this->shapes[$name] ??= new EntityShape($this->description->entity($name));
    }

    /** The class's name, a proxy's or a lazy object's parent where the description has no such class. */
    private function name(string|object $class): string
    {
        $name = \is_object($class) ? $class::class : ltrim($class, '\\');
        while (!isset($this->description->entities[$name]) && class_exists($name) && false !== ($parent = get_parent_class($name))) {
            if (!$this->isProxy($name)) {
                break;
            }
            $name = $parent;
        }

        return $name;
    }

    private function isProxy(string $class): bool
    {
        return str_contains($class, '\\__CG__\\') || is_subclass_of($class, 'Doctrine\\Persistence\\Proxy');
    }
}
