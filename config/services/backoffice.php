<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

/*
 * This file is part of the Glitchr package.
 *
 * (c) Marco Meyer <marco.meyer@glitchr.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * base-bundle's own CRUD controllers on the base-bundle-admin stack.
 * Autoconfigured so the admin's CrudControllerInterface autoconfiguration
 * tags them (routes + menu registration); apps override any of them by
 * declaring a controller for the same entity under App\ - the convention
 * lookup prefers the App variant (the historical cross-bundle behavior).
 */
return function (ContainerConfigurator $configurator) {

    $services = $configurator->services();
    $services->defaults()
        ->autowire(true)
        ->autoconfigure(true)
        ->public(false);

    if (class_exists('Base\\Admin\\Controller\\AbstractCrudController')) {
        $services->load('Base\\Controller\\Backoffice\\', dirname(__DIR__, 2) . '/src/Controller/Backoffice/')
            ->tag('controller.service_arguments');
    }
};
