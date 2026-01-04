<?php

namespace Base\Admin\Factory;

use Base\Admin\Config\Extension;
use Base\Admin\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\CrudControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\DashboardControllerInterface;
use Symfony\Component\HttpFoundation\Request;

class AdminContextFactory extends \EasyCorp\Bundle\EasyAdminBundle\Factory\AdminContextFactory
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
        $adminContext = object_dehydrate($adminContext);    
        
        return new AdminContext(
            $request, 
            $adminContext["user"], 
            $adminContext["i18nDto"],
            $adminContext["crudControllers"], 
            $adminContext["dashboardDto"], 
            $adminContext["dashboardControllerInstance"], 
            $adminContext["assetDto"], 
            $adminContext["crudDto"], 
            $adminContext["entityDto"], 
            $adminContext["searchDto"], 
            $adminContext["menuFactory"], 
            $adminContext["templateRegistry"], 
            $adminContext["usePrettyUrls"], 
            $this->extension
        );
    }
}
