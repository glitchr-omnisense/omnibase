<?php

namespace Base\Config\Menu;

use Base\Config\MenuItem;

use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\MenuItemTrait;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Menu\MenuItemInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\MenuItemDto;
use Exception;

class RouteMenuItem implements MenuItemInterface
{
    use MenuItemTrait;
    
    public function __construct(string $routeName, array $routeParameters, ?string $label = null, ?string $icon = null)
    {
        if (MenuItem::$translator == null)
            throw new Exception("Translator is missing");
        if (MenuItem::$router == null)
            throw new Exception("Router is missing");

        $this->dto = new MenuItemDto();

        $this->dto->setLinkUrl(MenuItem::$router->generate($routeName, $routeParameters));
        $this->dto->setLabel($label ?? MenuItem::$translator->trans("@controllers.".$routeName.".title"));
        $this->dto->setType(MenuItemDto::TYPE_URL);
        $this->dto->setIcon($icon);
        if($icon === null && MenuItem::$iconProvider != null) {
            $icons = MenuItem::$iconProvider->getRouteIcons($routeName);
            if($icons) $this->dto->setIcon(closest($icons, 1) ?? null);
        }
    }
}
