<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

/*
 * This file is part of the Glitchr package.
 *
 * (c) Marco Meyer <marco.meyer@glitchr.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * Real-time collaboration plumbing (autosave conflict guard today, presence
 * and live sync later) for EditorType and, eventually, regular form fields —
 * kept in its own domain file per the "split per domain" convention (see
 * services.php).
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->defaults()
        ->public(false);

    $services->set('Base\Service\Collab\CollabRoomResolver')
        ->public(true)
        ->args([
            new Reference('doctrine.orm.entity_manager'),
        ]);

    // Empty-string defaults so the container still compiles for apps that
    // haven't deployed the relay yet — collab_live stays fully opt-in;
    // CollabTicketFactory::isConfigured() refuses to mint a ticket unless
    // both are actually set, rather than silently signing with "".
    $container->parameters()->set('env(COLLAB_TICKET_SECRET)', '');
    $container->parameters()->set('env(COLLAB_RELAY_WS_URL)', '');

    $services->set('Base\Service\Collab\CollabTicketFactory')
        ->public(true)
        ->args([
            '%env(COLLAB_TICKET_SECRET)%',
            '%env(COLLAB_RELAY_WS_URL)%',
        ]);
};
