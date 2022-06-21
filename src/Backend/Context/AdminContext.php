<?php

namespace Base\Backend\Context;

use Base\Backend\Config\Extension;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\DashboardControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\AssetsDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\CrudDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\DashboardDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\I18nDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Factory\MenuFactory;
use EasyCorp\Bundle\EasyAdminBundle\Registry\CrudControllerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Registry\TemplateRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

class AdminContext extends \EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext
{
    protected ?Extension $extension = null;

    public function __construct(Request $request, ?UserInterface $user, I18nDto $i18nDto, CrudControllerRegistry $crudControllers, DashboardDto $dashboardDto, DashboardControllerInterface $dashboardController, AssetsDto $assetDto, ?CrudDto $crudDto, ?EntityDto $entityDto, ?SearchDto $searchDto, MenuFactory $menuFactory, TemplateRegistry $templateRegistry, Extension $extension)
    {
        parent::__construct(
            $request, $user, $i18nDto, $crudControllers, $dashboardDto, $dashboardController,
            $assetDto, $crudDto, $entityDto, $searchDto, $menuFactory, $templateRegistry);

        $this->extension = $extension;
    }

    public function getExtension()
    {
         return $this->extension;
    }

    public function getTranslationDomain()
    {
        return $this->dashboardDto->getTranslationDomain();
    }

}
