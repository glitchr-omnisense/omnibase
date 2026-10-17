<?php

namespace Base\Bridge\Symfony;

use Base\Contract\Urls;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Base\Contract\Urls under Symfony: the router (omnibase's advanced_router,
 * which generates through the application's) and the asset packages - what
 * Twig's path(), url() and asset() answer, the same strings.
 */
final class RouterUrls implements Urls
{
    public function __construct(
        private readonly UrlGeneratorInterface $router,
        private readonly ?Packages $packages = null,
    ) {
    }

    public function path(string $name, array $parameters = []): string
    {
        return $this->router->generate($name, $parameters, UrlGeneratorInterface::ABSOLUTE_PATH);
    }

    public function url(string $name, array $parameters = []): string
    {
        return $this->router->generate($name, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function asset(string $path): string
    {
        return $this->packages?->getUrl($path) ?? $path;
    }

    /**
     * Any of the router's reference types, beyond the contract (which has an
     * absolute path and URL only): Base\Contract\Link::to() asks it for a
     * relative or a network path, which the router made before C3e.
     */
    public function generate(string $name, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        return $this->router->generate($name, $parameters, $referenceType);
    }
}
