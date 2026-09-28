<?php

namespace Base\Twig\Extension;

use HWI\Bundle\OAuthBundle\Security\Http\ResourceOwnerMapLocator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * oauth_available('google'): whether a "continue with ..." button can lead
 * anywhere - the provider is declared in hwi_oauth.resource_owners of some
 * firewall AND has a client id. A site that leaves the id empty (a sandbox,
 * a provider not set up yet) keeps the declaration without showing a button
 * that ends on the provider's error page.
 */
final class OAuthTwigExtension extends AbstractExtension
{
    public function __construct(private readonly ?ResourceOwnerMapLocator $locator = null)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('oauth_available', $this->isAvailable(...))];
    }

    public function isAvailable(string $name): bool
    {
        foreach ($this->locator?->getResourceOwnerMaps() ?? [] as $map) {
            $owner = $map->hasResourceOwnerByName($name) ? $map->getResourceOwnerByName($name) : null;
            if (null !== $owner && '' !== trim((string) $owner->getOption('client_id'))) {
                return true;
            }
        }

        return false;
    }
}
