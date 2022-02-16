<?php

namespace Base\Entity\Layout\Widget;

use Doctrine\ORM\Mapping as ORM;

use Base\Entity\Layout\WidgetTranslation;

/**
 * @ORM\Entity()
 */

class LinkTranslation extends WidgetTranslation
{
    public function getTitle(): string
    {
        /**
         * @var Link
         */
        $translatable = $this->getTranslatable();
        return $translatable->getTitle() ?? $translatable->getHyperlink()->getTitle() ?? $translatable->__iconize();
    }
}
