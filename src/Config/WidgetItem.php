<?php

namespace Base\Config;

use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

use Base\Config\Menu\CrudWidgetItem;
use Base\Config\Menu\SectionWidgetItem;
use Base\Controller\Dashboard\AbstractCrudController;
use Base\Model\IconizeInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\DashboardMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\ExitImpersonationMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\LogoutMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\RouteMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\SectionMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\SubMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\UrlMenuItem;

class WidgetItem
{
    public static $adminUrlGenerator;
    public static $adminContextProvider;

    public static function setAdminUrlGenerator(AdminUrlGenerator $adminUrlGenerator)
    {
        self::$adminUrlGenerator = $adminUrlGenerator;
    }

    public static function setAdminContextProvider(AdminContextProvider $adminContextProvider)
    {
        self::$adminContextProvider = $adminContextProvider;
    }

    public static function linkToCrud(string $entityFqcn, ?string $label = null, ?string $icon = null): CrudWidgetItem
    {
        $crudController = AbstractCrudController::getCrudControllerFqcn($entityFqcn);
        $label = $label ?? $crudController::getTranslationPrefix().".plural";

        if(!$icon) {
            $icon = class_implements_interface($entityFqcn, IconizeInterface::class) ? $entityFqcn::__iconize()[0] : null;
            $icon = $crudController::getPreferredIcon() ?? $icon ?? "fas fa-question-circle";
        }

        return new CrudWidgetItem($label, $icon, $entityFqcn);
    }

    public static function linkToDashboard(string $label, ?string $icon = null): DashboardMenuItem
    {
        return new DashboardMenuItem($label, $icon);
    }

    public static function linkToExitImpersonation(string $label, ?string $icon = null): ExitImpersonationMenuItem
    {
        return new ExitImpersonationMenuItem($label, $icon);
    }

    public static function linkToLogout(string $label, ?string $icon = null): LogoutMenuItem
    {
        return new LogoutMenuItem($label, $icon);
    }

    public static function linkToRoute(string $label, ?string $icon = null, string $routeName, array $routeParameters = []): RouteMenuItem
    {
        return new RouteMenuItem($label, $icon, $routeName, $routeParameters);
    }

    public static function linkToUrl(string $label, ?string $icon, string $url): UrlMenuItem
    {
        return new UrlMenuItem($label, $icon, $url);
    }
    public static function section(?string $label = null, ?string $icon = null, int $width = 1): SectionWidgetItem
    {
        return new SectionWidgetItem($label, $icon, $width);
    }

    public static function subMenu(string $label, ?string $icon = null): SubMenuItem
    {
        return new SubMenuItem($label, $icon);
    }
}