<?php

namespace Base\Database\Schema;

/**
 * omnibase's own mapping attributes (#[DiscriminatorEntry], #[Vault],
 * #[OrderColumn]...) read by reflection, without instantiating them and
 * without the Doctrine-bound AttributeReader: their arguments by parameter
 * name, a positional one named after the parameter it fills. What the
 * description's rules read, where the ORM's listeners read the instances.
 *
 * @internal
 */
final class Attributes
{
    /**
     * The last attribute of that class written on the class itself (not on a parent), null if none.
     *
     * @param class-string $className
     * @param class-string $attributeClass
     *
     * @return array<string, mixed>|null
     */
    public static function ofClass(string $className, string $attributeClass): ?array
    {
        $attributes = (new \ReflectionClass($className))->getAttributes($attributeClass);

        return $attributes ? self::arguments(end($attributes)) : null;
    }

    /**
     * The attributes of that class on the properties of the class (the inherited ones among them), by property.
     *
     * @param class-string $className
     * @param class-string $attributeClass
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public static function ofProperties(string $className, string $attributeClass): array
    {
        $found = [];
        foreach ((new \ReflectionClass($className))->getProperties() as $property) {
            foreach ($property->getAttributes($attributeClass) as $attribute) {
                $found[$property->getName()][] = self::arguments($attribute);
            }
        }

        return $found;
    }

    /**
     * @param \ReflectionAttribute<object> $attribute
     *
     * @return array<string, mixed>
     */
    private static function arguments(\ReflectionAttribute $attribute): array
    {
        $parameters = [];
        if (class_exists($attribute->getName())) {
            foreach ((new \ReflectionClass($attribute->getName()))->getConstructor()?->getParameters() ?? [] as $parameter) {
                $parameters[] = $parameter->getName();
            }
        }

        $arguments = [];
        foreach ($attribute->getArguments() as $key => $value) {
            $arguments[is_int($key) ? ($parameters[$key] ?? (string) $key) : $key] = $value;
        }

        return $arguments;
    }
}
