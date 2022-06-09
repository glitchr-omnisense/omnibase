<?php

namespace Base\Config\Menu;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Menu\MenuItemInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\MenuItemDto;

use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\MenuItemTrait;

final class SeparatorWidgetItem implements MenuItemInterface
{
    protected $width;
    protected $column;
    use MenuItemTrait {
        setLinkRel as private;
        setLinkTarget as private;
    }

    public function __construct()
    {
        $this->dto   = new MenuItemDto();
        $this->dto->setType("separator");
    }
}
