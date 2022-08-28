<?php

namespace Base\Controller\Backend\Crud\Layout\Widget\Set;

use Base\Controller\Backend\Crud\Layout\WidgetCrudController;
use Base\Entity\Layout\Attribute\Adapter\HyperpatternAdapter;
use Base\Field\AttributeField;

class LinksetCrudController extends WidgetCrudController
{
    public static function getPreferredIcon(): ?string { return null; }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, [
            "id" => fn() => yield AttributeField::new('hyperlinks')->setFilter(HyperpatternAdapter::class)->hideOnIndex()],
                    fn() => yield AttributeField::new('hyperlinks')->setFilter(HyperpatternAdapter::class)->onlyOnIndex()
        , $args);
    }
}
