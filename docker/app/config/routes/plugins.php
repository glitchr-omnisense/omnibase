<?php

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

// The pages of the plugins the image does not install, when config/bundles.php registered them: the
// blog's, the classroom's and the agenda's, for their own suites (their checkouts mounted by compose.checkouts.yml).
return static function (RoutingConfigurator $routes): void {
    foreach (['Blog', 'Classroom', 'Agenda'] as $plugin) {
        if (class_exists('Base\\'.$plugin.'\\'.$plugin.'Bundle')) {
            $routes->import('@'.$plugin.'Bundle/src/Controller/Client', 'attribute')->prefix('/');
        }
    }
};
