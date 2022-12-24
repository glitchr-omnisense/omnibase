<?php

namespace Base\Backend\Factory;

use Base\Routing\RouterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\MenuItemDto;
use EasyCorp\Bundle\EasyAdminBundle\Menu\MenuItemMatcherInterface;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;

class MenuFactory extends \EasyCorp\Bundle\EasyAdminBundle\Factory\MenuFactory
{
    public function __construct(AdminContextProvider $adminContextProvider, AuthorizationCheckerInterface $authChecker, LogoutUrlGenerator $logoutUrlGenerator, AdminUrlGenerator $adminUrlGenerator, MenuItemMatcherInterface $menuItemMatcher, RouterInterface $router)
    {
        parent::__construct($adminContextProvider, $authChecker, $logoutUrlGenerator, $adminUrlGenerator, $menuItemMatcher);
        $this->router = $router;
    }

    protected function generateMenuItemUrl(MenuItemDto $menuItemDto): string
    {
        $menuItemType = $menuItemDto->getType();
        
        if (MenuItemDto::TYPE_EXIT_IMPERSONATION === $menuItemType) {

            $switchParameter = $this->router->getRouteFirewall()->getSwitchUser()["parameter"] ?? "_switch_user";
            return '?'.$switchParameter.'=_exit';
        }

        if (MenuItemDto::TYPE_SUBMENU === $menuItemType) {

            $url = $menuItemDto->getLinkUrl();
            $url = parse_url($url);
            $url["query"] ??= "";
            $url["query"] = explode_attributes("&", $url["query"]);
            $url["query"] = str_replace("\"", "", implode_attributes("&", $url["query"]));

            return compose_url($url["scheme"]  ?? null, $url["user"]      ?? null, $url["password"] ?? null,
                               $url["machine"] ?? null, $url["subdomain"] ?? null, $url["domain"]   ?? null, $url["port"] ?? null,
                               $url["path"]    ?? null, $url["query"]     ?? null);
        }

        if (MenuItemDto::TYPE_URL === $menuItemType) {

            $url = $menuItemDto->getLinkUrl();
            $url = parse_url($url);
            $url["query"] ??= "";
            $url["query"] = explode_attributes("&", $url["query"]);
            $url["query"] = str_replace("\"", "", implode_attributes("&", $url["query"]));

            return compose_url($url["scheme"]  ?? null, $url["user"]      ?? null, $url["password"] ?? null,
                               $url["machine"] ?? null, $url["subdomain"] ?? null, $url["domain"]   ?? null, $url["port"] ?? null,
                               $url["path"]    ?? null, $url["query"]     ?? null);
        }

        return parent::generateMenuItemUrl($menuItemDto);
    }
}
