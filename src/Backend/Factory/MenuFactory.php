<?php

namespace Base\Backend\Factory;

use Base\Routing\RouterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\MenuItemDto;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;

class MenuFactory extends \EasyCorp\Bundle\EasyAdminBundle\Factory\MenuFactory
{
    public function __construct(AdminContextProvider $adminContextProvider, AuthorizationCheckerInterface $authChecker, LogoutUrlGenerator $logoutUrlGenerator, AdminUrlGenerator $adminUrlGenerator, RouterInterface $router)
    {
        parent::__construct($adminContextProvider, $authChecker, $logoutUrlGenerator, $adminUrlGenerator);
        $this->router = $router;
    }

    protected function generateMenuItemUrl(MenuItemDto $menuItemDto, int $index, int $subIndex): string
    {
        $menuItemType = $menuItemDto->getType();

        if (MenuItemDto::TYPE_EXIT_IMPERSONATION === $menuItemType) {

            $switchParameter = $this->router->getRouteFirewall()->getSwitchUser()["parameter"] ?? "_switch_user";
            return '?'.$switchParameter.'=_exit';
        }

        return $menuItemDto->getLinkUrl() ?? parent::generateMenuItemUrl($menuItemDto, $index, $subIndex);
    }
}
