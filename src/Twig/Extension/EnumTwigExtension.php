<?php

namespace Base\Twig\Extension;

use Base\Service\Model\ColorizeInterface;
use Base\Service\TranslatorInterface;
use Omnibridge\Contract\Translations;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Base\View\Enums;

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
    private readonly Enums $enums;

    public function __construct(Translations $translations)
    {
        $this->enums = new Enums($translations);
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

    /**
     * @param string|list<string>|null $class
     *
     * @return mixed an enumeration (an instance of it, as the type was until step F2a), another type, or each of a list (Base\View\Enums)
     */
    public function enum(null|string|array $class): mixed
    {
        return $this->enums->enum($class);
    }

    /**
     * @param string|array<array-key, mixed>|ColorizeInterface|null $color
     *
     * @return string|array<array-key, mixed>|null
     */
    public function colorify(null|string|array|ColorizeInterface $color, ?string $entry = null): null|string|array
    {
        return $this->enums->colorify($color, $entry);
    }

    /** @param string|list<string>|null $options */
    public function transEnum(?string $value, string $class, null|string|array $options = TranslatorInterface::NOUN_SINGULAR): ?string
    {
        return $this->enums->transEnum($value, $class, $options);
    }

    /** @param string|list<string> $options */
    public function transEnumExists(string $value, string $class, string|array $options = TranslatorInterface::NOUN_SINGULAR): bool
    {
        return $this->enums->transEnumExists($value, $class, $options);
    }
}
