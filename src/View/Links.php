<?php

namespace Base\View;

use Base\Service\Model\LinkableInterface;

/**
 * Links (plan "omnibase agnostic", step F4): a link to an address or to a linkable record (|urlify)
 * and a linkable record's address (|linkify) - LinkTwigExtension's under Twig, the same code for
 * Blade and a React page's props. Whether an address is the page's own is the host's answer, a
 * closure (omnibase's router under Symfony, omnibridge/laravel's under Laravel).
 */
final class Links
{
    /**
     * @param (\Closure(mixed $urlOrPath): bool)|null $current whether an address is the page's own; none is without it
     */
    public function __construct(private readonly ?\Closure $current = null)
    {
    }

    /**
     * A link, its label the one given, else the record's or the address; "highlight" when it is
     * the page's own. Nothing without an address.
     *
     * @param array<string, mixed> $attributes
     */
    public function urlify(LinkableInterface|string|null $urlOrPath, ?string $label = null, array $attributes = []): string
    {
        $url = $urlOrPath;
        $label = $label ?? $urlOrPath;
        if ($urlOrPath instanceof LinkableInterface) {
            $url = $urlOrPath->__toLink();
            $label = $label ?? $urlOrPath->__toString();
        }

        if (null !== $this->current && ($this->current)($urlOrPath)) {
            $attributes['class'] = trim(($attributes['class'] ?? '').' highlight');
        }

        if (!$url) {
            return '';
        }

        return "<a href='".$url."' ".html_attributes($attributes).'>'.$label.'</a>';
    }

    /**
     * A linkable record's address, an address as it is.
     *
     * @throws \LogicException for an object that is not linkable
     */
    public function linkify(mixed $urlOrLinkableObject): ?string
    {
        $url = $urlOrLinkableObject;
        if (\is_object($urlOrLinkableObject)) {
            if (!$urlOrLinkableObject instanceof LinkableInterface) {
                throw new \LogicException('Object "'.$urlOrLinkableObject::class.'" received doesn\'t implement "'.LinkableInterface::class.'"');
            }
            $url = $urlOrLinkableObject->__toLink();
        }

        return \is_object($url) ? null : (\is_string($url) ? $url : null);
    }
}
