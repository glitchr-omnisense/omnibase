<?php

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

// The pages of the plugins the image does not install, when config/bundles.php registered them: the
// blog's, the classroom's, the agenda's, the scholar's, the press's and the consulting's, for their own suites (their checkouts mounted by compose.checkouts.yml).
return static function (RoutingConfigurator $routes): void {
    foreach (['Blog', 'Classroom', 'Agenda', 'Scholar', 'Press', 'Consulting'] as $plugin) {
        if (class_exists('Base\\'.$plugin.'\\'.$plugin.'Bundle')) {
            $routes->import('@'.$plugin.'Bundle/src/Controller/Client', 'attribute')->prefix('/');
        }
    }
};
