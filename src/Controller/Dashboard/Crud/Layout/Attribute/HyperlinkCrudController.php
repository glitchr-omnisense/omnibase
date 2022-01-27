<?php

namespace Base\Controller\Dashboard\Crud\Layout\Attribute;

use Base\Controller\Dashboard\AbstractCrudController;
use Base\Entity\Layout\Attribute\Abstract\HyperpatternAttribute;
use Base\Field\ArrayField;
use Base\Field\AssociationField;
use Base\Field\TranslationField;

use Base\Field\SelectField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;

class HyperlinkCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string { return null; }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function () {

            yield SelectField::new('hyperpattern')->setTextAlign(TextAlign::RIGHT)->setFilter(HyperpatternAttribute::class);
            
            yield ArrayField::new('value')->setPatternFieldName("hyperpattern.pattern");

        },$args);
    }
}
