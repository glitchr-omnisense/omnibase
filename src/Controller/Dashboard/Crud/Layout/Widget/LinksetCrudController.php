<?php

namespace Base\Controller\Dashboard\Crud\Layout\Widget;

use Base\Field\TranslationField;

use Base\Controller\Dashboard\Crud\Layout\WidgetCrudController;
use Base\Entity\Layout\Attribute\Abstract\HyperpatternAttribute;
use Base\Field\AttributeField;

class LinksetCrudController extends WidgetCrudController
{
    public static function getPreferredIcon(): ?string { return null; }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, ["id" => function () {

            yield AttributeField::new('hyperlinks')->setClass(HyperpatternAttribute::class);

        }], $args);
    }
}
