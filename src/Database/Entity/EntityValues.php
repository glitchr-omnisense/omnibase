<?php

namespace Base\Database\Entity;

use Base\Database\Entity\Extension\TranslatableInterface;
use Base\Database\Mapping\EntityShape;
use Base\Database\Mapping\EntityShapes;
use Base\Database\UnitOfWorks;
use Doctrine\Common\Collections\Collection;
use Exception;
use Omnibridge\Persistence\UnitOfWork;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * An entity's values as the behaviours read and write them, on the shapes of the unit of work's persistence (plan
 * "omnibase agnostic", step F7c): what omnibridge/doctrine's ClassMetadataManipulator does with Doctrine's metadata
 * (getFieldValue(), setFieldValue(), getPropertyValue(), setPropertyValue()), with omnibase's EntityShapes in its
 * place, so that the same calls give the same values whichever persistence keeps the entity.
 *
 * - A mapped property (a field or an association of the shape) is read and written as it is held, without its
 *   getter nor its setter and without loading a lazy object to write it - Doctrine's property accessors -, a field
 *   with an enumeration type as its backing value; any other property through Symfony's property accessor.
 * - A path (`translations.title`, `parent.slug`) is followed through the values, `translations` of a translatable
 *   entity being its translations; a key (`images[0]`) picks a list's or a collection's member.
 * - A lazy object is loaded before it is read (PHP 8.4's lazy ghosts, and Doctrine's proxies by name).
 *
 * AbstractAttribute's helpers use it when they are given the unit of work their hook's event carries (Slugify,
 * Uploader); without one they keep omnibridge/doctrine's ClassMetadataManipulator, deprecated until 6.0.
 */
final class EntityValues
{
    private const KEY_PATTERNS = ["/(\w*)\[(\w*)\]/", "/(\w*)%(\w*)%/", "/(\w*){(\w*)}/", "/(\w*)\((\w*)\)/"];

    /** @var \WeakMap<UnitOfWork, self>|null */
    private static ?\WeakMap $instances = null;

    private readonly PropertyAccessorInterface $accessor;

    /** @var array<string, \ReflectionProperty|false> */
    private static array $reflections = [];

    public function __construct(private readonly EntityShapes $shapes)
    {
        $this->accessor = PropertyAccess::createPropertyAccessor();
    }

    /** The values of the entities of that unit of work, on its persistence's shapes (one per unit of work). */
    public static function of(UnitOfWork $unitOfWork): self
    {
        self::$instances ??= new \WeakMap();

        return self::$instances[$unitOfWork] ??= new self(UnitOfWorks::shapes($unitOfWork));
    }

    public function shapes(): EntityShapes
    {
        return $this->shapes;
    }

    /** Whether the entity's class is one the persistence maps. */
    public function isEntity(object|string $entityOrClass): bool
    {
        return $this->shapes->has($entityOrClass);
    }

    /** Whether the property is a field or an association of the entity's class. */
    public function isMapped(object|string $entityOrClass, string $property): bool
    {
        if (!$this->shapes->has($entityOrClass)) {
            return false;
        }
        $shape = $this->shapes->shape($entityOrClass);

        return $shape->hasField($property) || $shape->hasAssociation($property);
    }

    /**
     * The value at the path (ClassMetadataManipulator::getFieldValue()): the entity itself for an empty path, null
     * for a null entity or one the persistence does not map.
     *
     * @throws Exception when the path cannot be followed
     */
    public function get(mixed $entity, string|array $path): mixed
    {
        if ('' === $path) {
            return $entity;
        }
        if (\is_string($path)) {
            $path = explode('.', $path);
        }
        if (empty($path)) {
            throw new Exception('No field path provided for "'.get_debug_type($entity).'" ');
        }
        if (!$entity) {
            return null;
        }
        if (\is_object($entity)) {
            self::load($entity);
        }

        $fieldName = array_shift($path);
        if (!\is_object($entity)) {
            if (!empty($fieldName)) {
                throw new Exception('Failed to find property "'.implode('.', array_merge([$fieldName], $path)).'" using "NULL" data');
            }

            return null;
        }
        $entityName = $entity::class;

        $fieldKey = preg_replace(self::KEY_PATTERNS, '$2', $fieldName, 1);
        if ($fieldKey) {
            $fieldName = preg_replace(self::KEY_PATTERNS, '$1', $fieldName, 1);
        } else {
            $fieldKey = null;
        }

        if (!$this->shapes->has($entity)) {
            return null;
        }

        if ($entity instanceof TranslatableInterface && 'translations' == $fieldName) {
            $fieldValue = $entity->getTranslations();
            $fieldName = implode('.', $path);
            $path = [];
        }

        $fieldValue = $this->isMapped($entity, $fieldName)
            ? $this->raw($entity, $fieldName)
            : $this->accessor->getValue($entity, $fieldName);

        if (\is_array($fieldValue)) {
            $entity = $fieldKey ? $fieldValue[$fieldKey] ?? null : null;
            $fieldValue = $fieldValue ?? begin($fieldValue);
        } elseif ($fieldValue instanceof Collection) {
            $fieldValue = $fieldValue->containsKey($fieldKey) ? $fieldValue->get($fieldKey ?? 0) : null;
        }

        if (!\is_object($fieldValue)) {
            if (empty($path)) {
                return $fieldValue;
            }

            throw new Exception('Failed to resolve property path "'.implode('.', array_merge([$fieldName], $path)).'" in "'.$entityName.'"');
        }

        if (!empty($path)) {
            return $this->get($fieldValue, implode('.', $path));
        }

        return $fieldValue;
    }

    /**
     * The field written as the persistence writes it (ClassMetadataManipulator::setFieldValue()): no setter, no lazy
     * load; an enumeration field given its backing value takes the case, a non-nullable typed property given null is
     * unset. A property the persistence does not map is written the same way.
     */
    public function set(object $entity, string $field, mixed $value): void
    {
        $reflection = $this->reflect($entity::class, $field) ?: throw new \InvalidArgumentException(sprintf('No property "%s" in %s.', $field, $entity::class));
        $enum = $this->enumOf($entity, $field);
        if (null !== $enum && null !== $value) {
            $value = self::toEnum($enum, $value);
        }
        if (null === $value && $reflection->hasType() && !$reflection->getType()->allowsNull()) {
            $declaring = $reflection->getDeclaringClass()->getName();
            $name = $reflection->getName();
            (function () use ($name): void { unset($this->{$name}); })->bindTo($entity, $declaring)();

            return;
        }
        $reflection->setRawValueWithoutLazyInitialization($entity, $value);
    }

    /**
     * A property read through its getter (ClassMetadataManipulator::getPropertyValue()): a column's name is read as
     * its field's; null for a property the entity has not.
     */
    public function property(?object $entity, string $property): mixed
    {
        if (!$entity) {
            return null;
        }
        $property = $this->fieldOfColumn($entity, $property);

        return property_exists($entity, $property) ? $this->accessor->getValue($entity, $property) : null;
    }

    /** A property written through its setter (ClassMetadataManipulator::setPropertyValue()). */
    public function setProperty(object $entity, string $property, mixed $value): void
    {
        $this->accessor->setValue($entity, $this->fieldOfColumn($entity, $property), $value);
    }

    /** The class declaring the property in the entity's hierarchy, as the shape says or as the class does. */
    public function declaringClass(object|string $entityOrClass, string $property): ?string
    {
        $class = \is_object($entityOrClass) ? $entityOrClass::class : $entityOrClass;
        if ($this->shapes->has($class)) {
            $shape = $this->shapes->shape($class);
            $declared = $shape->entity->fields[$property]->declaredIn ?? $shape->entity->relations[$property]->declaredIn ?? null;
            if (null !== $declared) {
                return $declared;
            }
        }
        $reflection = $this->reflect($class, $property);

        return $reflection ? $reflection->getDeclaringClass()->getName() : null;
    }

    private function raw(object $entity, string $property): mixed
    {
        $reflection = $this->reflect($entity::class, $property);
        if (!$reflection || !$reflection->isInitialized($entity)) {
            return null;
        }
        $value = $reflection->getRawValue($entity);
        if (null !== $value && null !== $this->enumOf($entity, $property)) {
            return \is_array($value) ? array_map(static fn ($case) => $case instanceof \BackedEnum ? $case->value : $case, $value) : ($value instanceof \BackedEnum ? $value->value : $value);
        }

        return $value;
    }

    /** @return class-string<\BackedEnum>|null */
    private function enumOf(object $entity, string $property): ?string
    {
        if (!$this->shapes->has($entity)) {
            return null;
        }
        $field = $this->shapes->shape($entity)->entity->fields[$property] ?? null;

        return $field?->enum;
    }

    private static function toEnum(string $enum, mixed $value): mixed
    {
        if (\is_array($value)) {
            return array_map(static fn ($member) => $member instanceof \BackedEnum ? $member : $enum::from($member), $value);
        }

        return $value instanceof \BackedEnum ? $value : $enum::from($value);
    }

    private function fieldOfColumn(object $entity, string $property): string
    {
        if (!$this->shapes->has($entity)) {
            return $property;
        }
        foreach ($this->shapes->shape($entity)->entity->fields as $name => $field) {
            if ($field->column === $property) {
                return $name;
            }
        }

        return $property;
    }

    private function reflect(string $class, string $property): \ReflectionProperty|false
    {
        $key = $class.'::'.$property;
        if (isset(self::$reflections[$key])) {
            return self::$reflections[$key];
        }
        for ($current = $class; false !== $current; $current = get_parent_class($current)) {
            if (property_exists($current, $property)) {
                $reflection = new \ReflectionProperty($current, $property);
                if ($reflection->getDeclaringClass()->getName() === $current || !$reflection->isPrivate()) {
                    return self::$reflections[$key] = new \ReflectionProperty($reflection->getDeclaringClass()->getName(), $property);
                }
            }
        }

        return self::$reflections[$key] = false;
    }

    /** A lazy object loaded before it is read: PHP 8.4's lazy ghosts and proxies, and Doctrine's proxies (by name). */
    public static function load(object $entity): void
    {
        $reflection = new \ReflectionClass($entity);
        if ($reflection->isUninitializedLazyObject($entity)) {
            $reflection->initializeLazyObject($entity);
        } elseif (is_a($entity, 'Doctrine\Persistence\Proxy') && !$entity->__isInitialized()) {
            $entity->__load();
        }
    }
}
