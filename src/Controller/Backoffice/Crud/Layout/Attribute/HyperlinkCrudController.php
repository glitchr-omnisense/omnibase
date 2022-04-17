<?php

namespace Base\Controller\Backoffice\Crud\Layout\Attribute;

use Base\Controller\Backoffice\AbstractCrudController;
use Base\Entity\Layout\Attribute\Abstract\HyperpatternAttribute;
use Base\Field\ArrayField;

use Base\Field\SelectField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class HyperlinkCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string { return null; }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function () {

            yield TextField::new('label');
            yield SelectField::new('hyperpattern')->setTextAlign(TextAlign::RIGHT)
                                                  ->setFilter(HyperpatternAttribute::class);

            yield ArrayField::new('value')->setPatternFieldName("hyperpattern.pattern");

        },$args);
    }
}
