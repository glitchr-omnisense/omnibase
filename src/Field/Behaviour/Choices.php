<?php

namespace Base\Field\Behaviour;

use Base\Field\Choice\Countries;
use Base\Form\Common\NativeEnum;

/**
 * The choices a choosing kind offers when the field names none: an enum's
 * (a PHP enum's cases, or one of omnibase's EnumType or SetType values: the
 * role, the state, the gender), the countries, the locales - what Symfony's
 * types guess or load themselves (FormGuessTrait::guessChoices(),
 * CountryType, LocaleType).
 */
final class Choices
{
    private function __construct()
    {
    }

    /**
     * label => value, groups as nested lists; null when the class offers none.
     *
     * @return array<int|string, mixed>|null
     */
    public static function of(?string $class): ?array
    {
        if (null === $class || '' === $class) {
            return null;
        }
        if (NativeEnum::is($class)) {
            return NativeEnum::ids($class);
        }
        if (class_exists($class) && is_subclass_of($class, \Base\Database\Type\EnumType::class)) {
            $permitted = $class::getPermittedValuesByClass();

            return 1 == \count($permitted) ? begin($permitted) : $permitted;
        }

        return null;
    }

    /** @return array<string, string> name => code, as Symfony's LocaleType lists them */
    public static function locales(): array
    {
        return array_flip(\Symfony\Component\Intl\Locales::getNames());
    }

    /** @return array<string, string> */
    public static function countries(): array
    {
        return Countries::getChoices();
    }
}
