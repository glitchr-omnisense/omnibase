<?php

namespace Base\Backend\Config\Menu;

use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\MenuItemTrait;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Menu\MenuItemInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\MenuItemDto;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 *
 */
class SubMenuItem implements MenuItemInterface
{
    use MenuItemTrait {
        setLinkRel as private;
        setLinkTarget as private;
    }

    /** @var MenuItemInterface[] */
    protected array $subMenuItems = [];

    public function __construct(TranslatableInterface|string $label, ?string $icon = null, ?string $url = null)
    {
        $this->dto = new MenuItemDto();

        $this->dto->setType(MenuItemDto::TYPE_SUBMENU);
        $this->dto->setLabel($label);
        $this->dto->setIcon($icon);
        $this->dto->setLinkUrl($url);
    }

    /**
     * @param MenuItemInterface[] $subItems
     */
    public function setSubItems(array $subItems)
    {
        $this->subMenuItems = $subItems;

        return $this;
    }

    public function getAsDto(): MenuItemDto
    {
        $subItemDtos = [];
        foreach ($this->subMenuItems as $subItem) {
            $subItemDtos[] = $subItem->getAsDto();
        }

        $this->dto->setSubItems($subItemDtos);

        return $this->dto;
    }
}
