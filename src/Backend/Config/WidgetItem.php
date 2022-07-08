<?php

namespace Base\Backend\Config;

use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

use Base\Backend\Config\Menu\CrudWidgetItem;
use Base\Backend\Config\Menu\EntityWidgetItem;
use Base\Backend\Config\Menu\SectionWidgetItem;
use Base\Backend\Config\Menu\SeparatorWidgetItem;
use Base\Controller\Backend\AbstractCrudController;

use Base\Model\IconizeInterface;
use Base\Service\Translator;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\DashboardMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\ExitImpersonationMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\LogoutMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\RouteMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\SubMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\UrlMenuItem;

class WidgetItem
{
    public static $adminUrlGenerator;
    public static $adminContextProvider;
    public static $translator;

    public static function setAdminUrlGenerator(AdminUrlGenerator $adminUrlGenerator)
    {
        self::$adminUrlGenerator = $adminUrlGenerator;
    }

    public static function setAdminContextProvider(AdminContextProvider $adminContextProvider)
    {
        self::$adminContextProvider = $adminContextProvider;
    }

    public static function separator()
    {
        return new SeparatorWidgetItem();
    }

    public static function linkToCrud(string $entityFqcnOrCrudController, ?string $label = null, ?string $icon = null)
    {
        if (!is_instanceof($entityFqcnOrCrudController, \EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController::class)) {
            $crudController = AbstractCrudController::getCrudControllerFqcn($entityFqcnOrCrudController);
            $widgetItem     = EntityWidgetItem::class;
        } else {
            $crudController = $entityFqcnOrCrudController;
            $widgetItem     = CrudWidgetItem::class;
        }

        if(!class_exists($crudController))
            throw new \Exception("CRUD controller for \"".$entityFqcnOrCrudController."\" not found");

        $crudTranslationPrefix   = $crudController::getCrudTranslationPrefix();
        $entityTranslationPrefix = $crudController::getEntityTranslationPrefix();

        $label   = self::$translator->transQuiet($crudTranslationPrefix.".".Translator::NOUN_PLURAL);
        $label ??= self::$translator->transQuiet($entityTranslationPrefix.".".Translator::NOUN_PLURAL);
        $label ??= camel2snake(class_basename($entityFqcnOrCrudController), " ");

        if(!$icon) {
            $icon = class_implements_interface($entityFqcnOrCrudController, IconizeInterface::class) ? $entityFqcnOrCrudController::__iconizeStatic()[0] : null;
            $icon = $crudController::getPreferredIcon() ?? $icon ?? "fas fa-question-circle";
        }

        return new $widgetItem($label, $icon, $crudController);
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

    public static function linkToRoute(string $label, ?string $icon, string $routeName, array $routeParameters = []): RouteMenuItem
    {
        return new RouteMenuItem($label, $icon, $routeName, $routeParameters);
    }

    public static function linkToUrl(string $labelOrEntityFqcn, ?string $icon, string $url): UrlMenuItem
    {
        if(class_exists($labelOrEntityFqcn)) {

            $crudController          = AbstractCrudController::getCrudControllerFqcn($labelOrEntityFqcn);
            if(!class_exists($crudController))
                throw new \Exception("CRUD controller for \"".$labelOrEntityFqcn."\" not found");

            $crudTranslationPrefix   = $crudController::getCrudTranslationPrefix();
            $entityTranslationPrefix = $crudController::getEntityTranslationPrefix();

            $label   = self::$translator->transQuiet($crudTranslationPrefix.".".Translator::NOUN_PLURAL);
            $label ??= self::$translator->transQuiet($entityTranslationPrefix.".".Translator::NOUN_PLURAL);
            $label ??= camel2snake(class_basename($labelOrEntityFqcn), " ");

            if(!$icon) {
                $icon = class_implements_interface($labelOrEntityFqcn, IconizeInterface::class) ? $labelOrEntityFqcn::__iconizeStatic()[0] : null;
                $icon = $crudController::getPreferredIcon() ?? $icon ?? "fas fa-question-circle";
            }

        } else $label = $labelOrEntityFqcn;

        return new UrlMenuItem($label, $icon, $url);
    }
    public static function section(?string $label = null, ?string $icon = null, int $width = 1, int $column = null): SectionWidgetItem
    {
        return new SectionWidgetItem($label, $icon, $width, $column);
    }

    public static function subMenu(string $label, ?string $icon = null): SubMenuItem
    {
        return new SubMenuItem($label, $icon);
    }
}
