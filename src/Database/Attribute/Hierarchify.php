<?php

namespace Base\Database\Attribute;

use Base\Attributes\AbstractAttribute;
use Base\Attributes\AttributeReader;
use Base\Database\Mapping\Completor;
use Base\Database\Attribute\Extension\ExtensionOptionInterface;


/**
 * Class Hierarchify
 * package Base\Database\Attribute\Hierarchify.
 */

 #[\Attribute(\Attribute::TARGET_CLASS)]
class Hierarchify extends AbstractAttribute implements ExtensionOptionInterface 
{
    /**
     * @var array|string|null
     */
    public array|string $hierarchy;

    /**
     * @var string|null
     */
    public ?string $separator;

    public function __construct(string|array|null $hierarchy = null, ?string $separator = "/")
    {
        // Its levels, from the first: one ("articles") or several (["articles", "comments"], which were dropped).
        $this->hierarchy = is_string($hierarchy) ? [$hierarchy] : array_values($hierarchy ?? []);
        $this->separator = $separator ?? null;
    }

    /**
     * @param string $target
     * @param string|null $targetValue
     * @param $object
     * @return bool
     */
    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        return AttributeReader::TARGET_CLASS == $target;
    }

    /** The hierarchy and its separator, on the class (plan "omnibase agnostic", step E3). */
    public function complete(Completor $completor, string $target, ?string $targetValue = null): void
    {
        if (AttributeReader::TARGET_CLASS !== $target) {
            return;
        }

        // The class's repository answers the hierarchy (HierarchifyTrait): checked on the declaration, on any persistence.
        $repository = self::declaredRepository($completor->getName());
        if (null !== $repository && !method_exists($repository, 'getHierarchyTree')) {
            throw new \LogicException("Did you forget to use \"Base\\Attributes\\Traits\\HierarchifyTrait\" in $repository?");
        }

        $completor->entityHierarchy ??= $this->hierarchy;
        $completor->entityHierarchySeparator ??= $this->separator;
    }

    /** The repositoryClass its #[Entity] declares (Base\Mapping's or Doctrine's), null when none. */
    private static function declaredRepository(?string $className): ?string
    {
        if (null === $className || !class_exists($className)) {
            return null;
        }
        foreach ((new \ReflectionClass($className))->getAttributes() as $attribute) {
            if (str_ends_with($attribute->getName(), '\\Mapping\\Entity')) {
                $repository = $attribute->getArguments()['repositoryClass'] ?? null;

                return \is_string($repository) && class_exists($repository) ? $repository : null;
            }
        }

        return null;
    }
}
