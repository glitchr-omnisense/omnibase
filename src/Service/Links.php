<?php

namespace Base\Service;

use Base\Contract\Link;
use Omnibridge\Contract\Urls;

/**
 * A route's address by a router's reference type, made on the Urls contract
 * (plan "omnibase agnostic", step E1): what an entity's __toLink() asks
 * (Widget\Page, Widget\Attachment, ShortLink, Image, Semantic, Widget\Route),
 * through Base\Contract\Link::to(), its static door.
 *
 * The reference types are Symfony's UrlGeneratorInterface values, named by
 * Base\Contract\Link: an absolute path is Urls::path(), an absolute URL
 * Urls::url(). A relative path and a network path have no equivalent in the
 * contract; they are asked of the Urls implementation's own
 * generate($name, $parameters, $referenceType) when it has one (omnibridge/
 * symfony's RouterUrls: the router, as before), and refused otherwise.
 */
final class Links
{
    public function __construct(private readonly Urls $urls)
    {
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function to(string $name, array $parameters = [], int $referenceType = Link::ABSOLUTE_PATH): string
    {
        return match ($referenceType) {
            Link::ABSOLUTE_PATH => $this->urls->path($name, $parameters),
            Link::ABSOLUTE_URL => $this->urls->url($name, $parameters),
            default => method_exists($this->urls, 'generate')
                ? $this->urls->generate($name, $parameters, $referenceType)
                : throw new \InvalidArgumentException(sprintf('The Urls contract answers an absolute path or URL; %s cannot make reference type %d of route "%s".', get_debug_type($this->urls), $referenceType, $name)),
        };
    }
}
