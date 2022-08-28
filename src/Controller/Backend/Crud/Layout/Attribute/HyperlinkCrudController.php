<?php

namespace Base\Controller\Backend\Crud\Layout\Attribute;

use Base\Controller\Backend\AbstractCrudController;
use Base\Entity\Layout\Attribute\Adapter\HyperpatternAdapter;
use Base\Field\ArrayField;

use Base\Field\SelectField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

class HyperlinkCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string { return null; }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function () {

            yield TextField::new('label');
            yield SelectField::new('hyperpattern')->setTextAlign(TextAlign::RIGHT)
                                                  ->setFilter(HyperpatternAdapter::class);

            yield ArrayField::new('value')->setPatternFieldName("hyperpattern.pattern")->onlyOnForms();
            yield UrlField::new('generate');

        },$args);
    }
}
