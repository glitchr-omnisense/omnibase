<?php

namespace Base\Config\Menu;

use Base\Config\MenuItem;

use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\MenuItemTrait;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Menu\MenuItemInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\MenuItemDto;
use Exception;

class ControllerMenuItem implements MenuItemInterface
{
    use MenuItemTrait;
    
    public function __construct(string $routeName, array $routeParameters, ?string $label = null, ?string $icon = null)
    {
        if (MenuItem::$translator == null)
            throw new Exception("Translator is missing");

        $this->dto = new MenuItemDto();

        $this->dto->setRouteName($routeName);
        $this->dto->setRouteParameters($routeParameters);

        $this->dto->setLabel($label ?? MenuItem::$translator->trans("@controllers.".$routeName.".title"));
        $this->dto->setType(MenuItemDto::TYPE_ROUTE);

        $this->dto->setIcon($icon);
        if($icon === null && MenuItem::$iconService != null) 
            $this->dto->setIcon(closest(MenuItem::$iconService->getRouteIcons($routeName), 1) ?? null);
    }
}
