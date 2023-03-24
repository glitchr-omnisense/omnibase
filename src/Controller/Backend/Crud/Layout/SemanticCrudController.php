<?php

namespace Base\Controller\Backend\Crud\Layout;

use Base\Backend\Config\Extension;
use Base\Field\TranslationField;

use Base\Controller\Backend\AbstractCrudController;
use Base\Controller\Backend\AbstractDashboardController;
use Base\Entity\Layout\Semantic;
use Base\Field\ArrayField;
use Base\Field\RouteField;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use Symfony\Component\Form\Extension\Core\Type\UrlType;

class SemanticCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string
    {
        return null;
    }

    public function configureExtensionWithResponseParameters(Extension $extension, KeyValueStore $responseParameters): Extension
    {
        return $extension;
    }
    public function createEntity(string $entityFqcn)
    {
        return new Semantic("");
    }
    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function () {
//            yield RouteField::new('routeName')->setColumns(6)->hideOnIndex();
            yield ArrayField::new('routeParameters')->setColumns(6)
                ->setPatternFieldName("routeName")->useAssociativeKeys()
                ->setLabel("Route Parameters")->hideOnIndex();

//            yield TranslationField::new("label")->renderAsHtml()
//                ->setFields([
//                    "label" => ["required" => true],
//                    "keywords" => ["tags" => true, "tokenSeparators" => [",", ";"]],
//                ]);
        }, $args);
    }
}
