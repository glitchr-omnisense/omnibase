<?php

namespace Base\Backend\Config\Menu;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Menu\MenuItemInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\MenuItemDto;

use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\MenuItemTrait;

/**
 *
 */
final class SectionWidgetItem implements MenuItemInterface
{
    use MenuItemTrait {
        setLinkRel as private;
        setLinkTarget as private;
    }

    protected int $width;
    protected ?int $column;

    /**
     * @return int
     */
    public function getWidth()
    {
        return $this->width;
    }

    public function setWidth(int $width): self
    {
        $this->width = $width;
        return $this;
    }

    /**
     * @return int
     */
    public function getColumn()
    {
        return $this->column ?? $this->width;
    }

    public function setColumn(int $column): self
    {
        $this->column = $column;
        return $this;
    }

    public function __construct(?string $label, ?string $icon, int $width = 1, ?int $column = null)
    {
        $this->width = $width;
        $this->column = $column;

        $this->dto = new MenuItemDto();
        $this->dto->setType(MenuItemDto::TYPE_SECTION);
        $this->dto->setLabel($label ?? '');
        $this->dto->setIcon($icon ?? "fa-solid fa-circle-question");
    }
}
