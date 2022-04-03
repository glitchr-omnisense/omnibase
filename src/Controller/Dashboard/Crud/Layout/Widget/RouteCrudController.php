<?php

namespace Base\Controller\Dashboard\Crud\Layout\Widget;

use Base\Controller\Dashboard\Crud\Layout\WidgetCrudController;

use Base\Field\ArrayField;
use Base\Field\RouteField;

class RouteCrudController extends WidgetCrudController
{
    public static function getPreferredIcon(): ?string { return null; }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName,
        ["id" => fn() => yield ArrayField::new('routeParameters')->setColumns(6)->setPatternFieldName("url")->useAssociativeKeys()->setLabel("Route")->hideOnIndex()],
        ["id" => fn() => yield RouteField::new('routeName')->setColumns(6)->hideOnIndex()],
        fn() => yield ArrayField::new('routeParameters')->setPatternFieldName("url")->setLabel("Route")->onlyOnIndex(), $args);
    }
}
