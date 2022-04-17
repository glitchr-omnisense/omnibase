<?php

namespace Base\Controller\Backoffice\Crud\Layout\Widget;

use Base\Controller\Backoffice\Crud\Layout\WidgetCrudController;
use Base\Entity\Layout\Attribute\Abstract\HyperpatternAttribute;
use Base\Field\AttributeField;

class LinksetCrudController extends WidgetCrudController
{
    public static function getPreferredIcon(): ?string { return null; }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, [
            "id" => fn() => yield AttributeField::new('hyperlinks')->setFilter(HyperpatternAttribute::class)->hideOnIndex()],
                    fn() => yield AttributeField::new('hyperlinks')->setFilter(HyperpatternAttribute::class)->onlyOnIndex()
        , $args);
    }
}
