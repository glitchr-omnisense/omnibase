<?php

namespace Base\Bridge\Symfony;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * What an action written once for every host is given under Symfony beyond
 * its route's parameters (step R27 of the audit "lesringostars face au
 * master plan"): `array $query`, the query string's values, as
 * omnibridge/laravel's Screen\Responder::action() gives them. A route
 * parameter of that name keeps its own.
 */
final class ActionArguments implements ValueResolverInterface
{
    /** @return iterable<array<string, mixed>> */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if ('query' !== $argument->getName() || 'array' !== $argument->getType() || $request->attributes->has('query')) {
            return [];
        }

        return [$request->query->all()];
    }
}
