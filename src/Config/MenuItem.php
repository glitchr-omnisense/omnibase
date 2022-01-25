<?php

namespace Base\Config;

use Base\Config\Menu\ControllerMenuItem;
use Base\Config\Menu\DropdownMenuItem;
use Base\Service\IconService;
use Base\Service\TranslatorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\CrudMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\DashboardMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\ExitImpersonationMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\LogoutMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\RouteMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\SectionMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\SubMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\UrlMenuItem;

class MenuItem
{
    public static $iconService;
    public static $translator;
    
    public static function setIconService(IconService $iconService)
    {
        self::$iconService = $iconService;
    }

    public static function setTranslator(TranslatorInterface $translator)
    {
        self::$translator = $translator;
    }

    public static function linkToCrud(string $entityFqcn, ?string $label = null, ?string $icon = null): CrudMenuItem
    {
        return new CrudMenuItem($label, $icon, $entityFqcn);
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

    public static function linkToRoute(string $routeName, array $routeParameters = [], ?string $label = null, ?string $icon = null): RouteMenuItem
    {
        return new RouteMenuItem($label, $icon, $routeName, $routeParameters);
    }

    public static function linkToUrl(string $label, ?string $icon, string $url): UrlMenuItem
    {
        return new UrlMenuItem($label, $icon, $url);
    }

    public static function section(?string $label = null, ?string $icon = null): SectionMenuItem
    {
        return new SectionMenuItem($label, $icon);
    }

    public static function subMenu(string $label, ?string $icon = null): SubMenuItem
    {
        return new SubMenuItem($label, $icon);
    }

    public static function dropdownMenu(string $label, ?string $icon = null, string $url): DropdownMenuItem
    {
        return new DropdownMenuItem($label, $icon, $url);
    }

    public static function linkToController(string $routeName, array $routeParameters = [], ? string $label = null, ?string $icon = null): ControllerMenuItem
    {
        return new ControllerMenuItem($routeName, $routeParameters, $label, $icon);
    }
}
