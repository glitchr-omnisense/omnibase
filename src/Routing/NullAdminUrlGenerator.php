<?php

namespace Base\Routing;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Stand-in used when glitchr/base-bundle-admin is not installed.
 *
 * Every call site in base-bundle builds its URL fluently and only then asks
 * for the string, so the builder methods stay silent no-ops and the failure
 * surfaces at generateUrl() - the one point where the caller genuinely needs
 * a back-office that isn't there.
 */
final class NullAdminUrlGenerator implements AdminUrlGeneratorInterface
{
    public function setController(string $controllerFqcn): static
    {
        return $this;
    }

    public function setAction(string $action): static
    {
        return $this;
    }

    public function setEntityId(mixed $entityId): static
    {
        return $this;
    }

    public function set(string $paramName, mixed $paramValue): static
    {
        return $this;
    }

    public function unset(string $paramName): static
    {
        return $this;
    }

    public function unsetAll(): static
    {
        return $this;
    }

    public function generateUrl(int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        throw new \LogicException('Cannot generate a back-office URL: no admin is installed. Require glitchr/base-bundle-admin, or guard the call site.');
    }
}
