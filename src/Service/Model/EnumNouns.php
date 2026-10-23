<?php

namespace Base\Service\Model;

use Base\Database\Type\SetType;
use Base\Service\Translator;
use Base\Service\TranslatorInterface;
use Doctrine\DBAL\Types\Type;
use Omnibridge\Contract\Translations;

/**
 * The words of an enumeration's values (omnibase's `|trans_enum`): a value of
 * an Base\Database\Type\EnumType - by its class or by the name of its DBAL
 * type ("thread_state") - read from the "enums" catalogues through the
 * Translations contract, under the key of the class that declares the value
 * and its parents down to the type (Base\Enum\ThreadState: "thread_state"),
 * the value lower-cased, the noun's variant its options ask for
 * (Base\Service\Model\NounVariants), the first one the catalogues hold.
 *
 * It was Base\Service\Translator::transEnum() and transEnumExists(), which
 * now answer by an instance of this class over the translator itself (as
 * EntityNouns for the entities): Symfony and a host without it say an
 * enumeration's values alike. Framework-free: the Translations contract, and
 * Doctrine DBAL's types where a type is named rather than its class.
 */
class EnumNouns
{
    public const DOMAIN = Translator::DOMAIN_ENUM;

    public function __construct(private readonly Translations $translations)
    {
    }

    /**
     * The words of $value; when the catalogues have none, the key asked in
     * lower case; $value itself for a class or a type that is none.
     *
     * @param string|list<string>|null $options
     */
    public function value(?string $value, string $class, null|string|array $options = TranslatorInterface::NOUN_SINGULAR): ?string
    {
        if (class_exists($class)) {
            $declaringClass = $class;
        } elseif (Type::hasType($class)) {
            $declaringClass = get_class(Type::getType($class));
        } else {
            return $value;
        }

        $id = $this->key($value, $class, $declaringClass);
        if (!$id) {
            return null;
        }

        if (!is_array($options)) {
            $options = array_filter([$options]);
        }

        [$suffixes, $asked, $ownLevel] = NounVariants::of($options);

        // A level the call gave is the one: the site's is not asked on top of it.
        $parameters = $ownLevel ? [Translator::TRANSLATION_POLITENESS => "none"] : [];

        $text = null;
        foreach ($suffixes as $suffix) {
            $candidate = mb_strtolower($id . $suffix);
            if ($this->translations->exists($candidate, self::DOMAIN)) {
                $text = $this->translations->trans($candidate, $parameters, self::DOMAIN);
                break;
            }
        }

        if (!$text && empty($options)) {
            throw new \LogicException("No translation found for \"@" . self::DOMAIN . "." . $id . "\" and no permutation option provided");
        }

        return $text ? mb_ucfirst($text) : mb_strtolower($id . implode("", $asked));
    }

    /**
     * Whether the catalogues hold the words of $value, under one of the keys
     * value() tries ($class an enumeration's class).
     *
     * @param string|list<string> $options
     */
    public function exists(string $value, string $class, string|array $options = TranslatorInterface::NOUN_SINGULAR): bool
    {
        $id = $this->key($value, $class, $class);
        if (!$id) {
            return false;
        }

        if (!is_array($options)) {
            $options = [$options];
        }

        [$suffixes] = NounVariants::of($options);
        foreach ($suffixes as $suffix) {
            if ($this->translations->exists(mb_strtolower($id . $suffix), self::DOMAIN)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The key of a value: the class that declares it (the type's, or a
     * parent of it up to Doctrine's Type) and its parents in snake case,
     * less the abstract types at the end, then the value
     * ("thread_state.published").
     */
    private function key(?string $value, string $class, string $declaringClass): string
    {
        while ((count(array_filter($declaringClass::getPermittedValues(false), fn ($c) => $c === $value)) == 0)) {
            $declaringClass = get_parent_class($declaringClass);
            if ($declaringClass === Type::class) {
                $declaringClass = $class;
                break;
            }
        }

        $value = $value ? "." . $value : "";
        $offset = is_subclass_of($class, SetType::class) ? -3 : -2;
        $key = implode(".", array_slice(explode(".", self::extends($declaringClass)), 0, $offset));

        return $key ? $key . $value : "";
    }

    /** A class and its parents, dotted, in snake case (Translator::parseClass(), PARSE_EXTENDS). */
    public static function extends(object|string $class): string
    {
        $class = is_object($class) ? get_class($class) : $class;
        $parent = class_exists($class) ? get_parent_class($class) : null;

        $name = class_name($class);
        if ($parent) {
            $name .= "." . class_name($parent);
        }
        while (class_exists($parent) && ($parent = get_parent_class($parent))) {
            $name .= "." . class_name($parent);
        }

        return camel2snake($name);
    }
}
