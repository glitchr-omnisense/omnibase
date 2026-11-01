<?php

namespace Base\View;

use Base\Service\TranslatorInterface;

/**
 * Words a page shows that its translations give (plan "omnibase agnostic", step F4): a
 * controller's title and excerpt (title(), excerpt()) and a value as text (|stringify) -
 * FunctionTwigExtension's under Twig, the same code for Blade and a React page's props.
 */
final class Captions
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    /** "<name>.title" in a domain; "@<domain>.<name>.title" when it is not translated. */
    public function title(string $name, array $parameters = [], ?string $domain = 'controllers', ?string $locale = null): string
    {
        return $this->caption($name.'.title', $parameters, $domain, $locale);
    }

    /** "<name>.excerpt" in a domain; "@<domain>.<name>.excerpt" when it is not translated. */
    public function excerpt(string $name, array $parameters = [], ?string $domain = 'controllers', ?string $locale = null): string
    {
        return $this->caption($name.'.excerpt', $parameters, $domain, $locale);
    }

    /**
     * Any value as text: a string as it is, a number, "true"/"false", "Array (n items)", an object
     * as its string or as "<its entity's noun> #<id>" (its uuid, else a short hash of the object).
     */
    public function stringify(mixed $value): string
    {
        if (null === $value) {
            return '';
        }

        if (\is_string($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (\is_array($value)) {
            return sprintf('Array (%d items)', \count($value));
        }

        if (\is_object($value)) {
            if (is_stringeable($value)) {
                return (string) $value;
            }

            if (method_exists($value, 'getId')) {
                return sprintf('%s #%s', $this->translator->transEntity($value::class), $value->getId());
            } elseif (method_exists($value, 'getUuid')) {
                return sprintf('%s #%s', $this->translator->transEntity($value::class), $value->getUuid());
            }

            return sprintf('%s #%s', $this->translator->transEntity($value::class), substr(md5(spl_object_hash($value)), 0, 7));
        }

        return '';
    }

    private function caption(string $id, array $parameters, ?string $domain, ?string $locale): string
    {
        $ret = $this->translator->trans($id, $parameters, $domain, $locale);

        return $ret == $id ? '@'.$domain.'.'.$ret : $ret;
    }
}
