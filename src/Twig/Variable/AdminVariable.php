<?php

namespace Base\Twig\Variable;

use Base\Admin\Controller\AbstractCrudController;
use Base\Admin\Controller\AbstractDashboardController;
use Base\Routing\AdminUrlGeneratorInterface;
use Base\Routing\AdvancedRouterInterface;
use Base\Service\LauncherInterface;
use Base\Service\LocalizerInterface;
use Base\Service\MaintenanceProviderInterface;
use Base\Service\SettingBagInterface;
use Base\Service\SitemapperInterface;
use Base\Service\TranslatorInterface;
use Base\Admin\Config\Crud;
use Symfony\Component\HttpKernel\KernelInterface;

class AdminVariable extends SiteVariable
{
    protected AdminUrlGeneratorInterface $adminUrlGenerator;

    public function __construct(
        AdvancedRouterInterface              $router,
        SitemapperInterface          $sitemapper,
        TranslatorInterface          $translator,
        LocalizerInterface           $localizer,
        SettingBagInterface          $settingBag,
        MaintenanceProviderInterface $maintenanceProvider,
        LauncherInterface            $launcher,
        ?KernelInterface             $kernel,
        AdminUrlGeneratorInterface   $adminUrlGenerator
    )
    {
        parent::__construct($router, $sitemapper, $translator, $localizer, $settingBag, $maintenanceProvider, $launcher, $kernel);
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    /**
     * @return mixed|string|null
     */
    public function title()
    {
        return $this->settingBag->getScalar("base.settings.title.admin")
            ?? $this->translator->trans('admin.title', [], AbstractDashboardController::TRANSLATION_DASHBOARD)
            ?? parent::title();
    }

    /**
     * @return mixed|string|null
     */
    public function slogan()
    {
        return $this->settingBag->getScalar("base.settings.slogan.admin")
            ?? $this->translator->trans('admin.slogan', [], AbstractDashboardController::TRANSLATION_DASHBOARD)
            ?? parent::title();
    }

    /**
     * @return mixed|null
     */
    public function logo()
    {
        return $this->settingBag->getScalar("base.settings.logo.admin")
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
            //->includeReferrer()
            ->generateUrl();
    }
}
