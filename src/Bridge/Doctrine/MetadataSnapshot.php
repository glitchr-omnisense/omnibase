<?php

namespace Base\Bridge\Doctrine;

use Base\Attributes\AttributeReader;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * What an entity manager made of omnibase's mapping, as plain data that can be
 * compared and written to JSON: for each class its ClassMetadata (every public
 * property - table, inheritance, discriminator map and value, fields and their
 * types, associations, cache, callbacks, repository...; the reflection left
 * out, the id generator by its class), what each enabled SQL filter adds to a
 * query of it, and the completor omnibase's attributes filled for it (aliases,
 * hierarchy); then the DBAL types registered, by name.
 *
 * A mapping object's properties left null or false are left out (read back
 * from the metadata cache, a field's nullable, unique and index are null where
 * a mapping just built says false); the discriminator map is sorted by its
 * keys, the subclasses by name and the property accessors by property - in a
 * map Doctrine generates, the classes come in the order PHP declared them,
 * which two processes need not share. Two entity managers over the same entities and database - DoctrineBundle's,
 * and one built by hand with Base\Bridge\Doctrine\Mapping - give the same
 * snapshot (tests/Bridge/Doctrine/HandBuiltEntityManagerTest).
 */
final class MetadataSnapshot
{
    /**
     * @param list<string>|null $classNames the classes, those the mapping driver knows when null
     *
     * @return array{classes: array<string, array<string, mixed>>, filters: array<string, string>, types: array<string, string>}
     */
    public static function of(EntityManagerInterface $entityManager, ?array $classNames = null): array
    {
        $classNames ??= $entityManager->getConfiguration()->getMetadataDriverImpl()->getAllClassNames();
        $classNames = array_values(array_unique($classNames));
        sort($classNames);

        $filters = [];
        foreach ($entityManager->getFilters()->getEnabledFilters() as $name => $filter) {
            $filters[$name] = $filter::class;
        }
        ksort($filters);

        $classes = [];
        foreach ($classNames as $className) {
            $classes[$className] = self::classMetadata($entityManager->getClassMetadata($className), $entityManager);
        }

        $types = [];
        foreach (Type::getTypeRegistry()->getMap() as $name => $type) {
            $types[$name] = $type::class;
        }
        ksort($types);

        return ['classes' => $classes, 'filters' => $filters, 'types' => $types];
    }

    /** @return array<string, mixed> */
    public static function classMetadata(ClassMetadata $classMetadata, EntityManagerInterface $entityManager): array
    {
        $data = get_object_vars($classMetadata);
        unset($data['reflClass'], $data['reflFields']);
        $data['idGenerator'] = isset($classMetadata->idGenerator) ? $classMetadata->idGenerator::class : null;
        $data = self::normalize($data);

        // In the order PHP declared the classes (the mapping driver lists them so), which two processes need not share;
        // the property accessors in the order the metadata was built or read back from the cache.
        ksort($data['discriminatorMap']);
        sort($data['subClasses']);
        if (is_array($data['propertyAccessors'] ?? null)) {
            ksort($data['propertyAccessors']);
        }

        $data['@filters'] = [];
        foreach ($entityManager->getFilters()->getEnabledFilters() as $name => $filter) {
            try {
                $data['@filters'][$name] = $filter->addFilterConstraint($classMetadata, 't0');
            } catch (\Throwable $e) {
                $data['@filters'][$name] = '! '.$e::class.': '.$e->getMessage();
            }
        }
        ksort($data['@filters']);

        $data['@completor'] = self::completor($classMetadata->getName());
        ksort($data);

        return $data;
    }

    /** The payload omnibase's attributes wrote for the class (aliasNames, entityHierarchy...), null without a reader. */
    private static function completor(string $className): ?array
    {
        $reader = AttributeReader::getInstance(false);
        if (!$reader instanceof AttributeReader) {
            return null;
        }

        $completor = $reader->getClassMetadataManipulator()->getClassMetadataCompletor($className);
        if ($completor === null) {
            return null;
        }

        $payload = (fn () => $this->payload)->call($completor);
        ksort($payload);

        return self::normalize($payload);
    }

    private static function normalize(mixed $value): mixed
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if (is_array($value)) {
            return array_map(self::normalize(...), $value);
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if ($value instanceof \ReflectionClass) {
            return $value->getName();
        }

        if ($value instanceof \ReflectionProperty) {
            return $value->class.'::'.$value->getName();
        }

        if ($value instanceof Type) {
            return $value::class;
        }

        if ($value instanceof \Closure) {
            return '@closure';
        }

        // A mapping object's unset properties (null) and its flags off (false) are left out: they say nothing, and a
        // mapping read back from the metadata cache has null where one just built says false (FieldMapping's nullable,
        // unique, index).
        $properties = method_exists($value, 'toArray') ? $value->toArray() : get_object_vars($value);

        return ['@class' => $value::class] + self::normalize(array_filter($properties, fn ($property) => $property !== null && $property !== false));
    }
}
