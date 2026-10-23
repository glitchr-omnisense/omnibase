<?php

namespace Base\Twig\Extension;

use Base\Database\Type\EnumType;
use Base\Service\Model\ColorizeInterface;
use Base\Service\Model\EnumNouns;
use Base\Service\TranslatorInterface;
use Doctrine\DBAL\Types\Type;
use Omnibridge\Contract\Translations;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * omnibase's enumerations in a template: `enum()` (an enumeration by its
 * class or by the name of its DBAL type, "thread_state"), `|colorify` (the
 * colours a ColorizeInterface gives, an enumeration's by value) and
 * `|trans_enum` / `|trans_enumExists` (a value's words, EnumNouns over the
 * Translations contract).
 *
 * Framework-free (audit "lesringostars face au master plan", R24): Symfony
 * registers it with omnibase's translator, Laravel (omnibridge/laravel) with
 * its catalogues - the same answers from the same code. enum() and colorify
 * were FunctionTwigExtension's, trans_enum TranslatorTwigExtension's, under
 * the same names. A type named by its DBAL name is known once Doctrine has
 * registered it: a host builds this extension after its entity manager.
 */
final class EnumTwigExtension extends AbstractExtension
{
    private EnumNouns $nouns;

    public function __construct(Translations $translations)
    {
        $this->nouns = new EnumNouns($translations);
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('enum', [$this, 'enum']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('colorify', [$this, 'colorify']),
            new TwigFilter('trans_enum', [$this, 'transEnum']),
            new TwigFilter('trans_enumExists', [$this, 'transEnumExists']),
        ];
    }

    public function enum(null|string|array $class): null|Type|array
    {
        if (\is_array($class)) {
            return array_map(fn($c) => $this->enum($c), $class);
        }

        if (class_exists($class) && is_instanceof($class, EnumType::class)) {
            return new $class();
        }

        return Type::hasType($class) ? Type::getType($class) : null;
    }

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

            $color = array_filter(array_map(fn($i) => $this->colorify($i), $color));
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
