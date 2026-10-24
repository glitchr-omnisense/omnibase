<?php

namespace Base\Entity\Layout\Widget;

use Doctrine\ORM\Mapping as ORM;

use Base\Entity\Layout\WidgetIntl;

#[ORM\Entity]
class LinkIntl extends WidgetIntl
{
    public function getTitle(): ?string
    {
        // Its own title, else its hyperlink's label. (Its icons came last before: an array where a string is
        // returned, a TypeError - which PHPStan saw once the translations' accessors were known, step R21b.)
        $translatable = $this->getTranslatable();

        return parent::getTitle() ?? ($translatable instanceof Link ? $translatable->getHyperlink()?->getLabel() : null);
    }
}
