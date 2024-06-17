<?php

namespace Base\Controller\Admin\Crud\Layout\Widget;

use Base\Controller\Admin\Crud\Layout\WidgetCrudController;

use Base\Field\ArrayField;
use Base\Field\RouteField;

/**
 *
 */
class RouteCrudController extends WidgetCrudController
{
    public static function getPreferredIcon(): ?string
    {
        return null;
    }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields(
            $pageName,
            ["id" => function () {
                yield RouteField::new('routeName')->setColumns(6)->hideOnIndex();
                yield ArrayField::new('routeParameters')->setColumns(6)/*->setPatternFieldName("routeName")*/ ->useAssociativeKeys()->setLabel("Route")->hideOnIndex();
            }]
        );
    }
}
