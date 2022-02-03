<?php

namespace Base\Entity\Layout\Widget;

use Doctrine\ORM\Mapping as ORM;

use Base\Database\Annotation\ColumnAlias;
use Base\Entity\Layout\WidgetTranslation;

/**
 * @ORM\Entity()
 */

class SlotTranslation extends WidgetTranslation
{
    /**
     * @ColumnAlias(column = "title")
     */
    protected $label;
    public function getLabel(): ?string { return $this->label; }
    public function setLabel(?string $label): self
    {
        $this->label = $label;
        return $this;
    }

    /**
     * @ColumnAlias(column = "excerpt")
     */
    protected $help;
    public function getHelp(): ?string { return $this->help; }
    public function setHelp(?string $help): self
    {
        $this->help = $help;
        return $this;
    }
}