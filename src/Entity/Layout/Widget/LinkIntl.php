<?php

namespace Base\Entity\Layout\Widget;

use Doctrine\ORM\Mapping as ORM;

use Base\Entity\Layout\WidgetIntl;

/**
 * @ORM\Entity()
 */
class LinkIntl extends WidgetIntl
{
    public function getTitle(): ?string
    {
        /**
         * @var Link $this
         */
        $translatable = $this->getTranslatable();
        return parent::getTitle() ?? $translatable->getHyperlink()->getLabel() ?? $translatable->__iconize();
    }
}
