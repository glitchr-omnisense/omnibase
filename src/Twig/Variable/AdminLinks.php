<?php

namespace Base\Twig\Variable;

use Base\Routing\AdminRouteNames;
use Omnibridge\Contract\Routes;
use Omnibridge\Contract\Urls;

/**
 * `app.admin` where omnibase/admin's generator is not at hand (a host other
 * than Symfony, whose back office stays the Symfony site's): crudify() is a
 * record's back-office edit page, named by omnibase/admin's rule
 * (AdminRouteNames::forEntity()), with the record's id - its address by the
 * host's Urls contract, null when the host's Routes know no such route.
 * Framework-free (audit "lesringostars face au master plan", R24); under
 * Symfony app.admin is AdminVariable, on omnibase/admin's own registry.
 */
final class AdminLinks
{
    public function __construct(private readonly Routes $routes, private readonly Urls $urls)
    {
    }

    public function crudify(mixed $entity): ?string
    {
        if (!\is_object($entity) || !method_exists($entity, 'getId') || null === $entity->getId()) {
            return null;
        }
        $route = AdminRouteNames::forEntity($entity::class, 'edit');

        return null !== $route && null !== $this->routes->pattern($route) ? $this->urls->path($route, ['entityId' => $entity->getId()]) : null;
    }
}
