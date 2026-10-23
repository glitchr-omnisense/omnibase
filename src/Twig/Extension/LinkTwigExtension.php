<?php

namespace Base\Twig\Extension;

use Base\Service\Model\LinkableInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Links in a template: `|urlify` (a link to an address or to a linkable
 * record, its label the one given, else the address; marked "highlight" when
 * it is the page's own) and `|linkify` (a linkable record's address).
 *
 * Framework-free (audit "lesringostars face au master plan", R24): whether
 * an address is the page's own is the host's answer, a closure - under
 * Symfony omnibase's router (MediaTwigExtension::links()), under Laravel
 * omnibridge/laravel's. They were MediaTwigExtension's, under the same names.
 */
final class LinkTwigExtension extends AbstractExtension
{
    /**
     * @param (\Closure(mixed $urlOrPath): bool)|null $current whether an address is the page's own; none is without it
     */
    public function __construct(private readonly ?\Closure $current = null)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('urlify', [$this, 'urlify'], ['is_safe' => ['all']]),
            new TwigFilter('linkify', [$this, 'linkify'], ['is_safe' => ['all']]),
        ];
    }

    /**
     * @param LinkableInterface|string|null $urlOrPath
     * @param string|null $label
     * @param array<string, mixed> $attributes
     * @return string
     */
    public function urlify(LinkableInterface|string|null $urlOrPath, ?string $label = null, array $attributes = [])
    {
        $url = $urlOrPath;
        $label = $label ?? $urlOrPath;
        if ($urlOrPath instanceof LinkableInterface) {
            $url = $urlOrPath->__toLink();
            $label = $label ?? $urlOrPath->__toString();
        }

        if (null !== $this->current && ($this->current)($urlOrPath)) {
            $attributes['class'] = trim(($attributes['class'] ?? '') . ' highlight');
        }

        if (!$url) {
            return '';
        }

        return "<a href='" . $url . "' " . html_attributes($attributes) . '>' . $label . '</a>';
    }

    /**
     * @param mixed $urlOrLinkableObject
     * @return string|null
     */
    public function linkify(mixed $urlOrLinkableObject)
    {
        $url = $urlOrLinkableObject;
        if (is_object($urlOrLinkableObject)) {

            if (!$urlOrLinkableObject instanceof LinkableInterface)
                throw new \LogicException("Object \"".get_class($urlOrLinkableObject)."\" received doesn't implement \"".LinkableInterface::class."\"");

            $url = $urlOrLinkableObject->__toLink();
        }

        return is_object($url) ? null : (is_string($url) ? $url : null);
    }
}
