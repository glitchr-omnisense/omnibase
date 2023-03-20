<?php

namespace Base\Backend\Factory;

use Base\Backend\Config\Extension;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Base\Backend\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\CrudControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\DashboardControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Factory\EntityFactory;
use EasyCorp\Bundle\EasyAdminBundle\Factory\MenuFactory;
use EasyCorp\Bundle\EasyAdminBundle\Registry\CrudControllerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class AdminContextFactory extends \EasyCorp\Bundle\EasyAdminBundle\Factory\AdminContextFactory
{
    protected $extension;
    public function __construct(string $cacheDir, ?TokenStorageInterface $tokenStorage, MenuFactory $menuFactory, CrudControllerRegistry $crudControllers, EntityFactory $entityFactory, Extension $extension)
    {
        parent::__construct($cacheDir, $tokenStorage, $menuFactory, $crudControllers, $entityFactory);
        $this->extension = $extension;
    }

    public function getExtension(): Extension
    {
        return $this->extension;
    }

    public function create(Request $request, DashboardControllerInterface $dashboardController, ?CrudControllerInterface $crudController): AdminContext
    {
        $crudAction = $request->query->get(EA::CRUD_ACTION);
        $validPageNames = [Crud::PAGE_INDEX, Crud::PAGE_DETAIL, Crud::PAGE_EDIT, Crud::PAGE_NEW];
        $pageName = \in_array($crudAction, $validPageNames, true) ? $crudAction : null;

        $dashboardDto = $this->getDashboardDto($request, $dashboardController);
        $assetDto = $this->getAssetDto($dashboardController, $crudController, $pageName);
        $actionConfigDto = $this->getActionConfig($dashboardController, $crudController, $pageName);
        $filters = $this->getFilters($dashboardController, $crudController);

        $crudDto = $this->getCrudDto($this->crudControllers, $dashboardController, $crudController, $actionConfigDto, $filters, $crudAction, $pageName);
        $entityDto = $this->getEntityDto($request, $crudDto);
        $searchDto = $this->getSearchDto($request, $crudDto);
        $i18nDto = $this->getI18nDto($request, $dashboardDto, $crudDto, $entityDto);
        $templateRegistry = $this->getTemplateRegistry($dashboardController, $crudDto);
        $user = $this->getUser($this->tokenStorage);
        $extension = $this->getExtension();

        return new AdminContext($request, $user, $i18nDto, $this->crudControllers, $dashboardDto, $dashboardController, $assetDto, $crudDto, $entityDto, $searchDto, $this->menuFactory, $templateRegistry, $extension);
    }
}
