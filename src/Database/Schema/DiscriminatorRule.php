<?php

namespace Base\Database\Schema;

use Base\Database\Attribute\DiscriminatorEntry;
use Omnibridge\Schema\Builder;
use Omnibridge\Schema\Builder\Draft;
use Omnibridge\Schema\Builder\Rule;
use Omnibridge\Schema\Declaration\ClassDeclaration;

/**
 * omnibase's discriminators, as Base\Bridge\Doctrine\MappingDriver and
 * #[DiscriminatorEntry] give them to Doctrine (plan "omnibase agnostique",
 * C6), on the description built without it:
 *
 * - once the declaration is read, the map of a root entity that declares an
 *   inheritance and no map is generated: the class and every subclass among
 *   the classes described, each under its own class name (Doctrine's default
 *   would take the short names, which collide: App\Entity\Thread\Tag,
 *   Base\Entity\Thread\Tag);
 * - once the class is whole, a root that carries #[DiscriminatorEntry] has the
 *   keys of its map renamed: each class of the map by its own entry's value -
 *   the value written, else the one DiscriminatorEntry::getValue() derives from
 *   the class's name and its parent's entry (common and abstract for a root of
 *   Base\Entity, the namespace below Entity\ in lower camel case joined by
 *   underscores, the parent's value in place of the namespace it covers). A
 *   subclass takes its parent's map as it is, and its own value in it.
 */
final class DiscriminatorRule implements Rule
{
    public function before(Draft $class, ClassDeclaration $declaration, Builder $builder): void
    {
    }

    public function declared(Draft $class, ClassDeclaration $declaration, Builder $builder): void
    {
        if ($class->isMappedSuperclass || $class->isEmbeddedClass || $class->isInheritanceTypeNone() || $class->discriminatorMap || !$class->isRootEntity()) {
            return;
        }

        $map = [$class->name => $class->name];
        foreach ($builder->classes() as $candidate) {
            if (is_subclass_of($candidate, $class->name)) {
                $map[$candidate] = $candidate;
            }
        }

        $class->setDiscriminatorMap($map);
    }

    public function loaded(Draft $class, ClassDeclaration $declaration, Builder $builder): void
    {
        if ($class->parentClasses || !$class->discriminatorMap || null === Attributes::ofClass($class->name, DiscriminatorEntry::class)) {
            return;
        }

        $values = [];
        foreach ($class->discriminatorMap as $className) {
            $values[$className] = self::value($className);
        }

        $class->discriminatorMap = array_flip($values);
        $class->discriminatorValue = $values[$class->name] ?? throw new \LogicException(sprintf('Missing discriminator entry in %s.', $class->name));
    }

    /** DiscriminatorEntry::getValue(), on the class's name and its parent's entry. @param class-string $className */
    public static function value(string $className): string
    {
        $entry = Attributes::ofClass($className, DiscriminatorEntry::class) ?? throw new \LogicException(sprintf('No #[DiscriminatorEntry] on %s.', $className));
        if (null !== ($entry['value'] ?? null)) {
            return (string) $entry['value'];
        }

        $namespace = explode("\\Entity\\", $className);
        $pos = array_search('Entity', $namespace, true);
        if (false !== $pos) {
            unset($namespace[$pos]);
        }

        $namespaceRoot = (string) array_shift($namespace);
        if (str_starts_with($namespaceRoot, 'Base')) {
            $namespacePrefix = explode("\\", $namespaceRoot);
            array_shift($namespacePrefix);

            $special = 'Base' === $namespaceRoot ? (is_abstract($className) ? 'abstract' : 'common') : null;
            if ($special && !get_parent_class($className)) {
                return $special;
            }

            $parts = array_unique(explode("\\", $namespace[0] ?? ''));
            $prefix = implode('_', array_map(mb_lcfirst(...), $namespacePrefix));

            return ($prefix ? $prefix.'_' : '').mb_lcfirst((string) end($parts));
        }

        $namespace = $namespace[0] ?? throw new \LogicException(sprintf('Unexpected location for "%s".', $className));

        $parentValue = null;
        $parentNamespace = null;
        if ($parentClassName = get_parent_class($className)) {
            $parentNamespace = explodeByArray("\\Entity\\", $parentClassName)[1] ?? null;
            if (null !== Attributes::ofClass($parentClassName, DiscriminatorEntry::class)) {
                $parentValue = self::value($parentClassName);
                $parentValue = in_array($parentValue, ['abstract', 'common'], true) ? null : $parentValue;
            }
        }

        if (null !== $parentNamespace && null !== $parentValue && str_starts_with($namespace, $parentNamespace."\\")) {
            $parts = explode("\\", (string) str_lstrip($namespace, $parentNamespace."\\"));
            array_unshift($parts, $parentValue);
        } else {
            $parts = explode("\\", $namespace);
        }

        return implode('_', array_map(mb_lcfirst(...), array_unique($parts)));
    }
}
