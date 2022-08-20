<?php

namespace Base\Controller\Backend\Crud\Layout;

use Base\Field\TranslationField;

use Base\Controller\Backend\AbstractCrudController;
use Base\Controller\Backend\AbstractDashboardController;
use Base\Entity\Layout\Semantic;
use Base\Field\ArrayField;
use Base\Field\RouteField;
use Symfony\Component\Form\Extension\Core\Type\UrlType;

class SemanticCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string { return null; }

    public function createEntity(string $entityFqcn) { return new Semantic(""); }
    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function () {

            yield RouteField::new('routeName')->setColumns(6)->hideOnIndex();
            yield ArrayField::new('routeParameters')->setColumns(6)->setPatternFieldName("routeName")->useAssociativeKeys()->setLabel("Route Parameters")->hideOnIndex();

            $url = parse_url(get_url());
            yield TranslationField::new("label")->renderAsHtml();
            yield TranslationField::new("url")->renderAsHtml()
                ->setFields([
                    "label" => ["required" => true],
                    "keywords" => [],
                    "url" => [
                        "form_type" => UrlType::class,
                        "attr" => ["placeholder" => $this->getTranslator()->trans("@".AbstractDashboardController::TRANSLATION_DASHBOARD.".crud.semantic.url.placeholder", [$url["scheme"]."://".$url["host"]])]
                    ]
                ]);

            }, $args);
    }
}
