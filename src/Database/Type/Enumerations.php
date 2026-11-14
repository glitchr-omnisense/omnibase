<?php

namespace Base\Database\Type;

/**
 * omnibase's enumerations by the name a column's type gives them (user_role => Base\Enum\UserRole,
 * or the application's App\Enum\UserRole): what code that holds a column's type asks to reach its
 * enumeration, whatever persistence maps it (plan "omnibase agnostic", step F2a). Filled by the
 * persistence when it registers them (Omnibridge\Doctrine\Omnibase\Types on Doctrine).
 */
final class Enumerations
{
    /** @var array<string, class-string<EnumType>> */
    private static array $classes = [];

    /** @param class-string<EnumType> $class */
    public static function register(string $name, string $class): void
    {
        self::$classes[$name] = $class;
    }

    /** @return class-string<EnumType>|null the enumeration a type of that name is (or the enumeration's class given), null for any other type */
    public static function classOf(?string $name): ?string
    {
        if (null === $name || '' === $name) {
            return null;
        }
        if (isset(self::$classes[$name])) {
            return self::$classes[$name];
        }

        return class_exists($name) && is_subclass_of($name, EnumType::class) ? $name : null;
    }

    /** @return array<string, class-string<EnumType>> every enumeration registered, by name */
    public static function all(): array
    {
        return self::$classes;
    }
}
