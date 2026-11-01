<?php

namespace Base\View;

use Omnibridge\Contract\Translations;
use Base\Database\Type\EnumType;
use Base\Database\Type\Enumerations;
use Base\Service\Model\ColorizeInterface;
use Base\Service\TranslatorInterface;
use Base\Service\Model\EnumNouns;

/**
 * omnibase's enumerations in a page (plan "omnibase agnostic", step F4): an enumeration by its
 * class or by its column type's name ("thread_state"), the colours a ColorizeInterface gives, a
 * value's words (EnumNouns over the Translations contract) - EnumTwigExtension's enum(),
 * |colorify and |trans_enum under Twig, the same code for Blade and a React page's props.
 */
final class Enums
{
    private readonly EnumNouns $nouns;

    public function __construct(Translations $translations)
    {
        $this->nouns = new EnumNouns($translations);
    }

    /**
     * An enumeration (an instance of it), by its class or by the name of its type; for any other
     * name, the persistence's type of that name where Doctrine is installed and knows it. Each of
     * a list for a list.
     *
     * @param string|list<string>|null $class
     */
    public function enum(null|string|array $class): mixed
    {
        if (\is_array($class)) {
            return array_map(fn ($c) => $this->enum($c), $class);
        }
        if (null === $class) {
            return null;
        }

        if (class_exists($class) && is_instanceof($class, EnumType::class)) {
            return new $class();
        }

        // A column's type by its name: its enumeration (an EnumTypeAdapter of it since step F2a), else the type.
        if (null !== ($enumeration = Enumerations::classOf($class))) {
            return new $enumeration();
        }

        $types = 'Doctrine\DBAL\Types\Type';

        return class_exists($types) && $types::hasType($class) ? $types::getType($class) : null;
    }

    /**
     * The colours of a ColorizeInterface (its entry when one is asked), of each of a list merged.
     *
     * @param string|array<array-key, mixed>|ColorizeInterface|null $color
     *
     * @return string|array<array-key, mixed>|null
     */
    public function colorify(null|string|array|ColorizeInterface $color, ?string $entry = null): null|string|array
    {
        if (!$color) {
            return $color;
        }

        if ($color instanceof ColorizeInterface) {
            $color = $color->__colorize() ?? $color->__colorizeStatic();

            return $color[$entry] ?? $color ?? null;
        }

        if (\is_array($color)) {
            $color = array_filter(array_map(fn ($i) => $this->colorify($i), $color));
            if ($color) {
                return array_merge(...$color);
            }
        }

        return null;
    }

    /** @param string|list<string>|null $options */
    public function transEnum(?string $value, string $class, null|string|array $options = TranslatorInterface::NOUN_SINGULAR): ?string
    {
        return $this->nouns->value($value, $class, $options);
    }

    /** @param string|list<string> $options */
    public function transEnumExists(string $value, string $class, string|array $options = TranslatorInterface::NOUN_SINGULAR): bool
    {
        return $this->nouns->exists($value, $class, $options);
    }
}
