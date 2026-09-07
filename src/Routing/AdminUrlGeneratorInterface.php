<?php

namespace Base\Routing;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Contract for building back-office URLs.
 *
 * base-bundle is usable on its own - a front-end-only install has no
 * back-office at all - so it must never type-hint the admin's concrete
 * generator. It depends on this interface instead; glitchr/base-bundle-admin
 * supplies the real implementation when it is installed, and
 * NullAdminUrlGenerator stands in when it is not.
 *
 * The fluent methods clone on first mutation, so a partially-built URL never
 * leaks between call sites.
 */
interface AdminUrlGeneratorInterface
{
    public function setController(string $controllerFqcn): static;

    public function setAction(string $action): static;

    public function setEntityId(mixed $entityId): static;

    public function set(string $paramName, mixed $paramValue): static;

    public function unset(string $paramName): static;

    public function unsetAll(): static;

    public function generateUrl(int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string;
}
