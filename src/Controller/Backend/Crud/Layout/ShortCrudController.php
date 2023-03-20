<?php

namespace Base\Controller\Backend\Crud\Layout;

use Base\Field\TranslationField;

use Base\Controller\Backend\AbstractCrudController;
use Base\Controller\Backend\AbstractDashboardController;
use Base\Entity\Layout\Short;
use Base\Field\SlugField;
use Symfony\Component\Form\Extension\Core\Type\UrlType;

class ShortCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string
    {
        return null;
    }

    public function createEntity(string $entityFqcn)
    {
        return new Short("");
    }
    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function () {
            yield SlugField::new('slug')->setColumns(6)->setRequired(false);

            $url = parse_url(get_url());

            yield TranslationField::new("label")->renderAsHtml();
            yield TranslationField::new("url")->renderAsHtml()
                ->setFields([
                    "label" => [],
                    "url" => [
                        "form_type" => UrlType::class,
                        "attr" => ["placeholder" => $this->getTranslator()->trans("@".AbstractDashboardController::TRANSLATION_DASHBOARD.".crud.short.url.placeholder", [$url["scheme"]."://".$url["host"]])]
                    ]
                ]);
        }, $args);
    }
}
