<?php

namespace Base\Admin\Factory;

use Base\Admin\Config\Extension;
use Base\Admin\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\CrudControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\DashboardControllerInterface;
use Symfony\Component\HttpFoundation\Request;

readonly class AdminContextFactory extends \EasyCorp\Bundle\EasyAdminBundle\Factory\AdminContextFactory
{
    protected Extension $extension;

    public function __construct(...$vars)
    {
        $this->extension = array_pop($vars);
        parent::__construct(...$vars);      
    }

    public function getExtension(): Extension
    {
        return $this->extension;
    }

    public function create(Request $request, DashboardControllerInterface $dashboardController, ?CrudControllerInterface $crudController, ?string $actionName = null): AdminContext
    {
        $adminContext = parent::create($request, $dashboardController, $crudController, $actionName);
        $props = object_dehydrate($adminContext);

        // Resolve constructor args by name so this works with any EasyCorp version
        // (old sub-object API uses keys like "requestContext"; new flat API uses "request", etc.)
        $ref = new \ReflectionClass(\EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext::class);
        $args = [];
        foreach ($ref->getConstructor()->getParameters() as $param) {
            $name = $param->getName();
            $args[] = array_key_exists($name, $props)
                ? $props[$name]
                : ($param->isDefaultValueAvailable() ? $param->getDefaultValue() : null);
        }
        $args[] = $this->extension;

        return new AdminContext(...$args);
    }
}
