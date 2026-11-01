<?php

namespace Base\View;

use Base\Service\Model\Color\Intl\Colors;
use Base\Service\Model\LinkableInterface;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * omnibase's formatting and value helpers, with no view engine (plan "omnibase agnostic", step F4):
 * what the Twig functions and filters of FunctionTwigExtension and ClassTwigExtension compute -
 * |highlight, mailto(), mailparse_email(), |pickup, |color_name, get_short_class()... -, the same
 * code for Twig (the extensions call it), Blade (omnibridge/laravel) and a React page's props.
 *
 * Pure: no service, no state. What needs a translator is Captions; dates are Dates.
 */
final class Formats
{
    /** A link to write to an address. */
    public static function mailto(string $address, string $label): string
    {
        return "<a href='mailto:".$address."'>".$label.'</a>';
    }

    /** The address of a recipient ("Name <address>", an address, a notifier's recipient). */
    public static function mailparseEmail(string|EmailRecipientInterface $recipient): ?string
    {
        $mailparse = mailparse($recipient instanceof EmailRecipientInterface ? $recipient->getEmail() : $recipient);

        return first(array_keys($mailparse));
    }

    /** The name of a recipient ("Name <address>"), its address when it has none. */
    public static function mailparseName(string|EmailRecipientInterface $recipient): ?string
    {
        $mailparse = mailparse($recipient instanceof EmailRecipientInterface ? $recipient->getEmail() : $recipient);

        return first($mailparse);
    }

    /**
     * The words of a text that match a pattern (or one of several) marked; with a gate, only the
     * first match and that many words around it, "[..]" where the text goes on. Null when nothing
     * matched within a gate.
     *
     * @param string|list<string> $pattern
     */
    public static function highlight(?string $content, string|array $pattern, int $gateLength = -1): ?string
    {
        if (!$pattern || !$content) {
            return $content;
        }

        if (\is_string($pattern)) {
            $pattern = preg_quote(str_strip_accents(strip_tags($pattern)));
        } else {
            $pattern = implode('|', array_map(fn ($p) => preg_quote(str_strip_accents(strip_tags($p))), $pattern));
        }

        $highlightContent = '';
        if ($gateLength < 0) {
            $highlightContent = preg_replace_callback(
                '/([^ ]*)('.$pattern.')([^ ]*)/im',
                function ($matches) {
                    if (empty($matches[2])) {
                        return $matches[0];
                    }

                    return '<mark class="markdown-word">'.
                        $matches[1].
                        '<mark class="markdown-pattern">'.$matches[2].'</mark>'.
                        $matches[3].
                        '</mark>';
                },
                $content
            );
        } elseif (preg_match_all('/((?:[^ ]+ ){0,'.$gateLength.'})([^ ]*)('.$pattern.')([^ ]*)((?: [^ ]+){0,'.$gateLength.'})/im', $content, $matches)) {
            $priorPatternGate = $matches[1][0] ?? '';
            $priorPattern = $matches[2][0] ?? '';
            $pattern = $matches[3][0] ?? ''; // (Case insensitive)
            $afterPattern = $matches[4][0] ?? '';
            $afterPatternGate = $matches[5][0] ?? '';

            $sentence = $priorPatternGate.$priorPattern.$pattern.$afterPattern.$afterPatternGate;

            if (!str_starts_with($content, $sentence)) {
                $highlightContent .= '[..] ';
            }

            $highlightContent .= "<mark class='markdown-gate'>";
            $highlightContent .= $priorPatternGate;
            $highlightContent .= "<mark class='markdown-word'>";
            $highlightContent .= $priorPattern;
            $highlightContent .= "<mark class='markdown-pattern'>";
            $highlightContent .= $pattern;
            $highlightContent .= '</mark>';
            $highlightContent .= $afterPattern;
            $highlightContent .= '</mark>';
            $highlightContent .= $afterPatternGate;
            $highlightContent .= '</mark>';

            if (!str_ends_with($content, $sentence)) {
                $highlightContent .= ' [..]';
            }
        }

        return empty($highlightContent) ? null : $highlightContent;
    }

    /** A string cut to a length, the replacement and ".." at its end; at a space if asked. */
    public static function truncate(mixed $string, mixed $maxLength = 30, mixed $replacement = '', bool $truncAtSpace = false): string
    {
        $maxLength -= \strlen($replacement);
        $length = \strlen($string);

        if ($length <= $maxLength) {
            return $string;
        }

        if ($truncAtSpace && ($space_position = strrpos($string, ' ', $maxLength - $length))) {
            $maxLength = $space_position;
        }

        return substr_replace($string, $replacement, $maxLength).'..';
    }

    /**
     * Whether more than that many seconds passed since a moment (a DateTime, a string, a timestamp).
     */
    public static function greaterThan(mixed $date, int $diff): bool
    {
        if (\is_string($date)) {
            $date = new \DateTime($date);
        }
        if ($date instanceof \DateTimeInterface) {
            $date = $date->getTimestamp();
        }

        return time() - $date > $diff;
    }

    /**
     * That many entries of a list taken at random, their keys kept.
     *
     * @param array<array-key, mixed>|null $array
     *
     * @return array<array-key, mixed>|null
     */
    public static function pickup(?array $array, int $i): ?array
    {
        if (null === $array) {
            return null;
        }

        $keys = array_rand($array, min(\count($array), $i)) ?? [];
        if (!\is_array($keys)) {
            $keys = [$keys];
        }

        return array_filter($array, fn ($k) => \in_array($k, $keys), ARRAY_FILTER_USE_KEY);
    }

    /** The nearest CSS colour name of a hexadecimal code. */
    public static function colorName(string $hex): ?string
    {
        return Colors::getName($hex);
    }

    /**
     * preg_split() with the subject first, as a filter reads it.
     *
     * @return list<string>|false
     */
    public static function pregSplit(string $subject, string $pattern, int $limit = -1, int $flags = 0): array|false
    {
        return preg_split($pattern, $subject, $limit, $flags);
    }

    /**
     * @param array<array-key, mixed> $array
     *
     * @return array<array-key, mixed>
     */
    public static function pad(array $array = [], int $length = 0, mixed $value = null): array
    {
        return array_pad($array, $length, $value);
    }

    /**
     * A list given to a closure, as it is without one.
     *
     * @param array<array-key, mixed> $array
     */
    public static function transforms(array $array = [], mixed $arrow = null): mixed
    {
        return $arrow instanceof \Closure ? $arrow($array) : $array;
    }

    /** The number of parameters a callable takes. */
    public static function nargs(callable $fn): int
    {
        return (new \ReflectionFunction(\Closure::fromCallable($fn)))->getNumberOfParameters();
    }

    public static function isLinkable(mixed $value): bool
    {
        return $value instanceof LinkableInterface;
    }

    public static function methodExists(mixed $object, string $method): bool
    {
        return $object && method_exists($object, $method);
    }

    public static function instanceOf(mixed $object, string $class): bool
    {
        return is_instanceof($object, $class);
    }

    /** An object's class without its namespace, its first letter lowered ("blogPost"). */
    public static function shortClass(object $object): string
    {
        $formattedClassName = explode('\\', $object::class);

        return lcfirst(end($formattedClassName));
    }

    /**
     * A static method of a class (or of an object's class) called.
     *
     * @throws \Exception when the class or the method does not exist
     */
    public static function staticCall(string|object $class, string $method, mixed ...$args): mixed
    {
        if (\is_object($class)) {
            $class = $class::class;
        }
        if (!class_exists($class)) {
            throw new \Exception("Cannot call static method $method on \"$class\": invalid class");
        }
        if (!method_exists($class, $method)) {
            throw new \Exception("Cannot call static method $method on \"$class\": invalid method");
        }

        return forward_static_call_array([$class, $method], $args);
    }

    /**
     * A static property of a class (or of an object's class).
     *
     * @throws \Exception when the class or the property does not exist
     */
    public static function staticProperty(string|object $class, string $propertyName): mixed
    {
        if (\is_object($class)) {
            $class = $class::class;
        }
        if (!class_exists($class)) {
            throw new \Exception("Cannot call static property $propertyName on \"$class\": invalid class");
        }
        if (!property_exists($class, $propertyName)) {
            throw new \Exception("Cannot call static property $propertyName on \"$class\": invalid property");
        }

        return $class::$$propertyName;
    }

    /**
     * A value read along a property path ("author.name", or the path's parts), null where a step
     * cannot be read.
     *
     * @param string|list<string> $propertyName
     */
    public static function propertyAccessor(mixed $object, array|string $propertyName, bool $enableMagicCall = false): mixed
    {
        if (null == $object) {
            return null;
        }

        // Shape property path
        $propertyPath = \is_string($propertyName) ? explode('.', $propertyName) : $propertyName;
        if (!$propertyPath) {
            return $object;
        }

        // Special case for array
        if ($object instanceof Collection) {
            $object = $object->toArray();
        }
        if (\is_array($object)) {
            $id = array_unshift($object);
            $object = $object[$id] ?? null;
        }

        // Extract head
        $propertyName = first($propertyPath);
        $propertyAccessorBuilder = PropertyAccess::createPropertyAccessorBuilder();
        if ($enableMagicCall) {
            $propertyAccessorBuilder->enableMagicCall();
        }

        $propertyAccessor = $propertyAccessorBuilder->getPropertyAccessor();
        if (!$propertyAccessor->isReadable($object, $propertyName)) {
            return null;
        }

        $object = $propertyAccessor->getValue($object, $propertyName);

        return self::propertyAccessor($object, tail($propertyPath)); // Recursive processing
    }
}
