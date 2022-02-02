<?php

namespace Base\Config;

use Base\Config\Menu\RouteMenuItem;
use Base\Service\IconService;
use Base\Service\TranslatorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\CrudMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\DashboardMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\ExitImpersonationMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\LogoutMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\SectionMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\SubMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\UrlMenuItem;
use Symfony\Component\Routing\RouterInterface;

class MenuItem
{
    public static $iconService;
    public static $translator;
    public static $router;
    
    public static function setIconService(IconService $iconService)
    {
        self::$iconService = $iconService;
    }

    public static function setTranslator(TranslatorInterface $translator)
    {
        self::$translator = $translator;
    }

    public static function setRouter(RouterInterface $router)
    {
        self::$router = $router;
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
        return new RouteMenuItem($routeName, $routeParameters, $label, $icon);
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
}
