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
            ["id" => fn() => yield RouteField::new('route')->hideOnIndex()],
            ["id" => fn() => yield ArrayField::new('routeParameters')->setPatternFieldName("url")->useAssociativeKeys()->setLabel("Route")->hideOnIndex()],
                     fn() => yield ArrayField::new('routeParameters')->setPatternFieldName("url")->setLabel("Route")->onlyOnIndex(), $args);
    }
}
