<?php

namespace Base\Admin\Factory;

use Base\Routing\AdvancedRouterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\MenuItemDto;

/**
 *
 */
class MenuFactory extends \EasyCorp\Bundle\EasyAdminBundle\Factory\MenuFactory
{
    /**
     * @var AdvancedRouterInterface
     */
    protected AdvancedRouterInterface $router;

    public function __construct(...$params)
    {
        $this->router = array_pop($params);
        parent::__construct(...$params);
    }

    protected function generateMenuItemUrl(MenuItemDto $menuItemDto): string
    {
        $menuItemType = $menuItemDto->getType();
        if (MenuItemDto::TYPE_EXIT_IMPERSONATION === $menuItemType) {
            $switchParameter = $this->router->getRouteFirewall()->getSwitchUser()["parameter"] ?? "_switch_user";
            return '?' . $switchParameter . '=_exit';
        }

        if (MenuItemDto::TYPE_SUBMENU === $menuItemType) {
            
            $url = $menuItemDto->getLinkUrl();
            $url = parse_url($url);
            $url["query"] ??= "";
            $url["query"] = explode_attributes("&", $url["query"]);
            $url["query"] = str_replace("\"", "", implode_attributes("&", $url["query"]));

            return compose_url(
                $url["scheme"] ?? null,
                $url["user"] ?? null,
                $url["password"] ?? null,
                $url["machine"] ?? null,
                $url["subdomain"] ?? null,
                $url["domain"] ?? null,
                $url["port"] ?? null,
                $url["path"] ?? null,
                $url["query"] ?? null,
                $url["fragment"] ?? null
            );
        }

        if (MenuItemDto::TYPE_URL === $menuItemType) {

            $url = $menuItemDto->getLinkUrl();
            $url = parse_url($url);

            $url["query"] ??= "";
            $url["query"] = explode_attributes("&", $url["query"]);
            $url["query"] = str_replace("\"", "", implode_attributes("&", $url["query"]));

            return compose_url(
                $url["scheme"] ?? null,
                $url["user"] ?? null,
                $url["password"] ?? null,
                $url["machine"] ?? null,
                $url["subdomain"] ?? null,
                $url["domain"] ?? null,
                $url["port"] ?? null,
                $url["path"] ?? null,
                $url["query"] ?? null
            );
        }

        return parent::generateMenuItemUrl($menuItemDto);
    }
}
