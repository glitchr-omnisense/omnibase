<?php

namespace Base\Bridge\PHPStan;

use Base\Database\Attribute\Alias;
use Base\Database\Entity\Extension\AliasTrait;
use Base\Database\Entity\Extension\TranslatableInterface;
use Base\Database\Entity\Extension\TranslationInterface;
use Omnibridge\Schema\CamelCaseNaming;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Reflection\ReflectionProvider;
use Symfony\Component\String\Inflector\EnglishInflector;

/**
 * The accessors an omnibase entity answers through __call() for PHPStan
 * (audit "lesringostars face au master plan", R21b), as FinderMethodsExtension
 * does for the repositories' finders:
 *
 * - #[Alias] (AliasTrait::__call()): get/set/is/has of an alias - the property
 *   carrying #[Alias(column: ...)], or the `alias` of a class-level
 *   #[Alias(column: ..., alias: ...)] - and add/remove of a class-level alias,
 *   as AliasTrait resolves them: Article::getDestination() is getParent(),
 *   Comment::getReplies() getChildren(), addReplies() addChild();
 * - translations (TranslatableTrait::__call()): a public method of the
 *   entity's translation class (X + "Intl", else its parent's), called on the
 *   entity - Location::getLabel() is TagIntl::getLabel() on translate().
 *
 * Each is known with the signature of the method it ends in, when there is
 * one (getDestination() returns what getParent() returns), any arguments and
 * mixed otherwise. A name neither rule gives - an alias that is no property,
 * a method no translation has - is still an undefined method.
 *
 * Registered by extension.neon. Loaded by PHPStan alone, never at run time.
 */
final class EntityAccessorsExtension implements MethodsClassReflectionExtension
{
    /** AliasTrait::__call()'s actions, then the alias. */
    public const PATTERN = '/^(get|set|add|remove|is|has)([A-Z][A-Za-z0-9_]*)$/';

    /** @var array<string, array{0: array<string, string>, 1: array<string, string>}> class => [aliases of properties, aliases of the class], alias => column */
    private array $aliases = [];

    public function __construct(private readonly ReflectionProvider $reflectionProvider)
    {
    }

    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        return null !== $this->target($classReflection, $methodName);
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        $target = $this->target($classReflection, $methodName);
        $translation = $target instanceof MethodReflection && $target->getDeclaringClass()->implementsInterface(TranslationInterface::class) && !$classReflection->implementsInterface(TranslationInterface::class);

        return new EntityAccessorReflection($classReflection, $methodName, $target instanceof MethodReflection ? $target : null, $translation);
    }

    /** The method the accessor ends in, true when it ends in a property alone, null when there is no such accessor. */
    private function target(ClassReflection $class, string $methodName): MethodReflection|bool|null
    {
        if (!$class->hasNativeMethod('__call')) {
            return null;
        }

        if (self::usesAliasTrait($class->getNativeReflection()) && 1 === preg_match(self::PATTERN, $methodName, $match)) {
            [$properties, $classLevel] = $this->aliases($class);
            $action = $match[1];
            $alias = lcfirst($match[2]);
            $column = $classLevel[$alias] ?? (\in_array($action, ['add', 'remove'], true) ? null : ($properties[$alias] ?? null));
            if (null !== $column) {
                $candidates = [$action.ucfirst($column)];
                if (\in_array($action, ['add', 'remove'], true)) {
                    $candidates = array_map(static fn (string $singular) => $action.ucfirst($singular), (new EnglishInflector())->singularize($column) ?: [$column]);
                }
                foreach ($candidates as $candidate) {
                    if ($class->hasNativeMethod($candidate)) {
                        return $class->getNativeMethod($candidate);
                    }
                }

                return true;
            }
        }

        if ($class->implementsInterface(TranslatableInterface::class)) {
            $translation = $this->translationClass($class);
            if (null !== $translation && $translation->hasNativeMethod($methodName) && $translation->getNativeMethod($methodName)->isPublic()) {
                return $translation->getNativeMethod($methodName);
            }
        }

        return null;
    }

    /**
     * Whether the class, a parent or a trait of theirs uses AliasTrait (TranslatableAliasTrait does).
     *
     * @param \ReflectionClass<object>|\PHPStan\BetterReflection\Reflection\Adapter\ReflectionClass|\PHPStan\BetterReflection\Reflection\Adapter\ReflectionEnum $class
     */
    private static function usesAliasTrait(object $class): bool
    {
        for ($current = $class; false !== $current; $current = $current->getParentClass()) {
            $traits = $current->getTraits();
            while ([] !== $traits) {
                $trait = array_pop($traits);
                if (AliasTrait::class === $trait->getName()) {
                    return true;
                }
                array_push($traits, ...array_values($trait->getTraits()));
            }
        }

        return false;
    }

    /** @return array{0: array<string, string>, 1: array<string, string>} */
    private function aliases(ClassReflection $class): array
    {
        if (isset($this->aliases[$class->getName()])) {
            return $this->aliases[$class->getName()];
        }

        $properties = $classLevel = [];
        for ($current = $class; null !== $current; $current = $current->getParentClass()) {
            $native = $current->getNativeReflection();
            foreach ($native->getAttributes(Alias::class) as $attribute) {
                [$column, $alias] = self::columnAndAlias($attribute->getArguments());
                if ('' !== $alias && '' !== $column) {
                    $classLevel[$alias] ??= $column;
                }
            }
            foreach ($native->getProperties() as $property) {
                foreach ($property->getAttributes(Alias::class) as $attribute) {
                    [$column] = self::columnAndAlias($attribute->getArguments());
                    if ('' !== $column) {
                        $properties[$property->getName()] ??= $column;
                    }
                }
            }
        }

        return $this->aliases[$class->getName()] = [$properties, $classLevel];
    }

    /**
     * Alias's constructor: (string $column = "", string $alias = "", ?string $type = null), named or not.
     *
     * @param array<int|string, mixed> $arguments
     *
     * @return array{0: string, 1: string}
     */
    private static function columnAndAlias(array $arguments): array
    {
        $column = $arguments['column'] ?? $arguments[0] ?? '';
        $alias = $arguments['alias'] ?? $arguments[1] ?? '';

        return [\is_string($column) ? $column : '', \is_string($alias) ? $alias : ''];
    }

    /** TranslatableTrait::getTranslationEntityClass(): X + "Intl" when it is a translation, else its parent's. */
    private function translationClass(ClassReflection $class): ?ClassReflection
    {
        for ($current = $class; null !== $current; $current = $current->getParentClass()) {
            $name = $current->getName().CamelCaseNaming::TABLE_I18N_SUFFIX;
            if ($this->reflectionProvider->hasClass($name)) {
                $translation = $this->reflectionProvider->getClass($name);
                if ($translation->implementsInterface(TranslationInterface::class)) {
                    return $translation;
                }
            }
        }

        return null;
    }
}
