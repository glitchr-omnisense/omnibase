<?php

namespace Base\Backend\Config\Menu;

use Base\Backend\Config\MenuItem;
use Base\Service\Translator;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\RouteMenuItem as EaRouteMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Dto\MenuItemDto;
use Exception;

/**
 *
 */
class RouteMenuItem extends EaRouteMenuItem
{
    public function __construct(string $routeName, array $routeParameters, ?string $label = null, ?string $icon = null)
    {
        if (MenuItem::$translator == null) {
            throw new Exception("Translator is missing");
        }
        if (MenuItem::$router == null) {
            throw new Exception("Router is missing");
        }

        $label = $label ? MenuItem::$translator->transQuiet($label, [], Translator::DOMAIN_BACKEND) : $label;

        $this->dto = new MenuItemDto();
        $this->dto->setLinkUrl(MenuItem::$router->generate($routeName, $routeParameters));
        $this->dto->setLabel($label ?? MenuItem::$translator->transRoute($routeName));
        $this->dto->setType(MenuItemDto::TYPE_URL);
        $this->dto->setIcon($icon);

        if ($icon === null && MenuItem::$iconProvider != null) {
            $icons = MenuItem::$iconProvider->getRouteIcons($routeName);
            if ($icons) {
                $this->dto->setIcon(closest($icons, 1) ?? null);
            }
        }
    }
}
