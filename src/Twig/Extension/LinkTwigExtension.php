<?php

namespace Base\Twig\Extension;

use Base\Service\Model\LinkableInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Base\View\Links;

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
    private readonly Links $links;

    public function __construct(private readonly ?\Closure $current = null)
    {
        $this->links = new Links($current);
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
        return $this->links->urlify($urlOrPath, $label, $attributes);
    }

    /**
     * @param mixed $urlOrLinkableObject
     * @return string|null
     */
    public function linkify(mixed $urlOrLinkableObject)
    {
        return $this->links->linkify($urlOrLinkableObject);
    }
}
