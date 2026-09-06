<?php

namespace Base\Database\Attribute;

use Base\Attributes\AbstractAttribute;
use Base\Attributes\AttributeReader;
use Base\Database\Entity\EntityExtensionInterface;
use Base\Database\Entity\Extension\TranslationInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Marks a property as worth remembering: every flush that changes it appends
 * the old value to the entity's revision history (see VersionableSubscriber,
 * which writes one Revision per entity per flush - not one per field).
 *
 * Pure metadata, deliberately: the subscriber needs to see ALL of an entity's
 * versioned fields at once to write a single coherent revision, which a
 * per-property lifecycle hook cannot do.
 *
 * Unlike Trasheable this needs no hierarchy walk - AttributeReader builds
 * property attributes from ReflectionClass::getProperties(), which does include
 * inherited properties, so `#[Versionable]` on Thread::$slug is seen on Article.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Versionable extends AbstractAttribute implements EntityExtensionInterface
{
    /**
     * Whether the history badge offers to restore this field, or only to read
     * it. Fields whose form widget has no value setter are shown read-only
     * rather than given a button that silently does nothing.
     */
    public bool $restorable;

    /**
     * Top-level keys ignored when deciding whether a JSON-valued field
     * changed. The stored diff still holds the real values - this only
     * governs equality.
     *
     * EditorJS is why this exists: its payload carries a "time" stamped at
     * save, so opening an article and saving it again without touching a word
     * yields a value that differs from the previous one in that field alone.
     * Left unfiltered, every save would append a ~28 KB revision recording
     * nothing, and five of those would be all the history an article ever had.
     */
    public array $ignore;

    public function __construct(bool $restorable = true, array $ignore = [])
    {
        $this->restorable = $restorable;
        $this->ignore = $ignore;
    }

    /**
     * The comparable form of a value: same value, minus the volatile keys.
     * A value that is not a JSON object is returned untouched.
     */
    public function comparable(mixed $value): mixed
    {
        if (empty($this->ignore) || !is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return $value;
        }

        foreach ($this->ignore as $key) {
            unset($decoded[$key]);
        }

        return json_encode($decoded);
    }

    /**
     * @param string $target
     * @param string|null $targetValue
     * @param $object
     * @return bool
     */
    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        return ($target == AttributeReader::TARGET_PROPERTY);
    }

    /**
     * Versioned property names for a class, mapped to their attribute.
     *
     * @return array<string, self>
     */
    public static function resolve(object|string $entityOrClass): array
    {
        if ($entityOrClass instanceof ClassMetadata) {
            $className = $entityOrClass->getName();
        } elseif (is_object($entityOrClass)) {
            $className = get_class($entityOrClass);
        } else {
            $className = $entityOrClass;
        }

        if (!class_exists($className)) {
            return [];
        }

        $fields = [];
        foreach (AttributeReader::getInstance()->getPropertyAttributes($className, self::class) as $property => $attributes) {
            if (!empty($attributes)) {
                $fields[$property] = end($attributes);
            }
        }

        return $fields;
    }

    /**
     * Where an entity's revisions live, and the prefix its field names carry
     * there.
     *
     * A translation is not versioned in its own right: its history belongs to
     * the translatable it translates, under a locale-prefixed key. Shared by
     * the subscriber that writes revisions and the form extension that reads
     * them back, so the two can never disagree about the key.
     *
     * @return array{0: ?object, 1: string}
     */
    public static function anchor(object $entity): array
    {
        if ($entity instanceof TranslationInterface) {
            $locale = $entity->getLocale();
            return [$entity->getTranslatable(), $locale ? $locale . "." : ""];
        }

        return [$entity, ""];
    }

    public static function has(object|string $entityOrClass, ?string $property = null): bool
    {
        $fields = self::resolve($entityOrClass);
        return $property === null ? !empty($fields) : array_key_exists($property, $fields);
    }
}
