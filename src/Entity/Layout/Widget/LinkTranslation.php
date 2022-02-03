<?php

namespace Base\Entity\Layout\Widget;

use Doctrine\ORM\Mapping as ORM;

use Base\Database\Annotation\ColumnAlias;
use Base\Database\Annotation\IsNullable;
use Base\Entity\Layout\WidgetTranslation;

/**
 * @ORM\Entity()
 * @IsNullable(column="title", value=true)
 */

class LinkTranslation extends WidgetTranslation
{
    public function getTitle(): string 
    {
        /**
         * @var Link
         */
        $translatable = $this->getTranslatable();
        return $translatable->getTitle() ?? 
               $translatable->getHyperlink()->getTitle() ?? 
               $translatable->__iconize();
    }
}