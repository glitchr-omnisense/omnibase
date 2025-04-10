<?php

namespace Base\Admin\Router;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Routing\Route;

class AdminRouteGenerator extends \EasyCorp\Bundle\EasyAdminBundle\Router\AdminRouteGenerator
{
    public function __construct(
        protected iterable $dashboardControllers,
        protected iterable $crudControllers,
        protected CacheItemPoolInterface $cache,
    ) { }

    protected function getDashboardsRouteConfig(): array
    {
        $config = parent::getDashboardsRouteConfig();
        foreach($config as &$entry) {
            $entry["routePath"] = dirname($entry["routePath"]);
        }

        return $config;
    }

    /**
     * @return array<string, Route>
     */
    protected function generateAdminRoutes(): array
    {
        /** @var array<string, Route> $adminRoutes Stores the collection of admin routes created for the app */
        $adminRoutes = [];
        /** @var array<string> $addedRouteNames Temporary cache that stores the route names to ensure that we don't add duplicated admin routes */
        $addedRouteNames = [];

        foreach ($this->dashboardControllers as $dashboardController) {
            $dashboardFqcn = $dashboardController::class;
            [$allowedCrudControllers, $deniedCrudControllers] = $this->getAllowedAndDeniedControllers($dashboardFqcn);
            $defaultRoutesConfig = $this->getDefaultRoutesConfig($dashboardFqcn);
            $dashboardRouteConfig = $this->getDashboardsRouteConfig()[$dashboardFqcn];
            foreach ($this->crudControllers as $crudController) {
                $crudControllerFqcn = $crudController::class;

                if (null !== $allowedCrudControllers && !\in_array($crudControllerFqcn, $allowedCrudControllers, true)) {
                    continue;
                }

                if (null !== $deniedCrudControllers && \in_array($crudControllerFqcn, $deniedCrudControllers, true)) {
                    continue;
                }

                $crudControllerRouteConfig = $this->getCrudControllerRouteConfig($crudControllerFqcn);
                $actionsRouteConfig = array_replace_recursive($defaultRoutesConfig, $this->getCustomActionsConfig($crudControllerFqcn));
                // by default, the 'detail' route uses a catch-all route pattern (/{entityId});
                // so, if the user hasn't customized the 'detail' route path, we need to sort the actions
                // to make sure that the 'detail' action is always the last one
                if ('/{entityId}' === $actionsRouteConfig['detail']['routePath']) {
                    uasort($actionsRouteConfig, static function ($a, $b) {
                        return match (true) {
                            'detail' === $a['routeName'] => 1,
                            'detail' === $b['routeName'] => -1,
                            default => 0,
                        };
                    });
                }

                foreach (array_keys($actionsRouteConfig) as $actionName) {
                    $actionRouteConfig = $actionsRouteConfig[$actionName];
                    $adminRoutePath = rtrim(sprintf('%s/%s/%s', $dashboardRouteConfig['routePath'], $crudControllerRouteConfig['routePath'], ltrim($actionRouteConfig['routePath'], '/')), '/');
                    $adminRouteName = sprintf('%s_%s_%s', $dashboardRouteConfig['routeName'], $crudControllerRouteConfig['routeName'], $actionRouteConfig['routeName']);

                    if (\in_array($adminRouteName, $addedRouteNames, true)
                        && !str_starts_with($crudControllerFqcn, "Base\\")
                    ) {
                        throw new \RuntimeException(sprintf('When using pretty URLs, all CRUD controllers must have unique PHP class names to generate unique route names. However, your application has at least two controllers with the FQCN "%s", generating the route "%s". Even if both CRUD controllers are in different namespaces, they cannot have the same class name. Rename one of these controllers to resolve the issue.', $crudControllerFqcn, $adminRouteName));
                    }

                    $defaults = [
                        '_controller' => $crudControllerFqcn.'::'.$actionName,
                    ];
                    $options = [
                        EA::ROUTE_CREATED_BY_EASYADMIN => true,
                        EA::DASHBOARD_CONTROLLER_FQCN => $dashboardFqcn,
                        EA::CRUD_CONTROLLER_FQCN => $crudControllerFqcn,
                        EA::CRUD_ACTION => $actionName,
                    ];

                    $adminRoute = new Route($adminRoutePath, defaults: $defaults, options: $options, methods: $actionRouteConfig['methods']);
                    $adminRoutes[$adminRouteName] = $adminRoute;
                    $addedRouteNames[] = $adminRouteName;
                }
            }
        }

        return $adminRoutes;
    }

    protected function transformCrudControllerNameToKebabCase(string $crudControllerFqcn): string
    {
        return str_replace("-", "_", $this->transformCrudControllerNameToSnakeCase($crudControllerFqcn));
    }

    protected function transformCrudControllerNameToSnakeCase(string $crudControllerFqcn): string
    {
        $crudControllerFqcn = explode("\\Crud\\", $crudControllerFqcn);
        $crudControllerFqcn = str_replace("\\", "", end($crudControllerFqcn));

        $shortName = strip_duplicates($crudControllerFqcn);
        $shortName = str_replace(['CrudController', 'Controller'], '', $shortName);

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName));
    }
}
