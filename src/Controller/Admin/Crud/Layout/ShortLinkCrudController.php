<?php

namespace Base\Controller\Admin\Crud\Layout;

use Base\Field\TranslationField;

use Base\Controller\Admin\AbstractCrudController;
use Base\Controller\Admin\AbstractDashboardController;
use Base\Entity\Layout\ShortLink;
use Base\Field\SlugField;
use Symfony\Component\Form\Extension\Core\Type\UrlType;

class ShortLinkCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string
    {
        return null;
    }

    /**
     * @param string $entityFqcn
     * @return ShortLink
     */
    public function createEntity(string $entityFqcn): object { return new ShortLink(""); }

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
                        "attr" => ["placeholder" => $this->getTranslator()->trans("@" . AbstractDashboardController::TRANSLATION_DASHBOARD . ".crud.shlink.url.placeholder", [$url["scheme"] . "://" . $url["host"]])]
                    ]
                ]);
        }, $args);
    }
}
