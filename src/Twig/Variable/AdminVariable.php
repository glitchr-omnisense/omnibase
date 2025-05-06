<?php

namespace Base\Twig\Variable;

use Base\Controller\Admin\AbstractCrudController;
use Base\Controller\Admin\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Base\Routing\AdvancedRouterInterface;
use Base\Service\BaseService;
use Base\Service\LauncherInterface;
use Base\Service\LocalizerInterface;
use Base\Service\MaintenanceProviderInterface;
use Base\Service\SitemapperInterface;
use Base\Service\TranslatorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;

/**
 *
 */
class AdminVariable extends SiteVariable
{
    protected AdminUrlGenerator $adminUrlGenerator;

    public function __construct(
        AdvancedRouterInterface              $router,
        SitemapperInterface          $sitemapper,
        TranslatorInterface          $translator,
        LocalizerInterface           $localizer,
        BaseService                  $baseService,
        MaintenanceProviderInterface $maintenanceProvider,
        LauncherInterface            $launcher,
        AdminUrlGenerator            $adminUrlGenerator
    )
    {
        parent::__construct($router, $sitemapper, $translator, $localizer, $baseService, $maintenanceProvider, $launcher);
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    /**
     * @return mixed|string|null
     */
    public function title()
    {
        return $this->baseService->getAdmin()['title']
            ?? $this->translator->trans('admin.title', [], AbstractDashboardController::TRANSLATION_DASHBOARD)
            ?? parent::title();
    }

    /**
     * @return mixed|string|null
     */
    public function slogan()
    {
        return $this->baseService->getAdmin()['slogan']
            ?? $this->translator->trans('admin.slogan', [], AbstractDashboardController::TRANSLATION_DASHBOARD)
            ?? parent::title();
    }

    /**
     * @return mixed|null
     */
    public function logo()
    {
        return $this->baseService->getAdmin()['logo']
            ?? parent::logo();
    }

    /**
     * @param mixed $entity
     * @return string|null
     */
    public function crudify(mixed $entity)
    {
        if (null == $entity) {
            return null;
        }

        $entityCrudController = AbstractCrudController::getCrudControllerFqcn($entity);
        if (null == $entityCrudController) {
            return null;
        }

        return $this->adminUrlGenerator->unsetAll()
            ->setController($entityCrudController)
            ->setEntityId($entity->getId())
            ->setAction(Crud::PAGE_EDIT)
            ->includeReferrer()
            ->generateUrl();
    }
}
