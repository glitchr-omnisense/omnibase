<?php

namespace Base\Admin\Router;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Routing\Route;

class AdminRouteGenerator extends \EasyCorp\Bundle\EasyAdminBundle\Router\AdminRouteGenerator
{
    protected function transformCrudControllerNameToKebabCase(string $crudControllerFqcn): string
    {
        $cleanShortName = str_replace(['CrudController', 'Controller'], '', (new \ReflectionClass($crudControllerFqcn))->getShortName());
        $snakeCaseName = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $cleanShortName));
        if (str_starts_with($crudControllerFqcn, 'Base\\')) {
            $snakeCaseName .= '-base';
        }

        return $snakeCaseName;
    }

    protected function transformCrudControllerNameToSnakeCase(string $crudControllerFqcn): string
    {
        $shortName = str_replace(['CrudController', 'Controller'], '', (new \ReflectionClass($crudControllerFqcn))->getShortName());
        if (str_starts_with($crudControllerFqcn, 'Base\\')) {
            $shortName .= '_base';
        }

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName));
    }
}
