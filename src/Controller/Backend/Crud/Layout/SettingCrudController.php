<?php

namespace Base\Controller\Backend\Crud\Layout;

use Base\Field\TranslationField;

use Base\Controller\Backend\AbstractCrudController;
use Base\Entity\Layout\Setting;
use Base\Field\BooleanField;
use Base\Field\SlugField;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

/**
 *
 */
class SettingCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string
    {
        return null;
    }

    /**
     * @param string $entityFqcn
     * @return Setting
     */
    public function createEntity(string $entityFqcn)
    {
        return new Setting("");
    }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function () {
            yield SlugField::new('path')->showLeadingHash(false)->setColumns(6)->keep("_")->setSeparator(".")->setTargetFieldName("translations.label");
            yield SlugField::new('bag')->showLeadingHash(false)->setColumns(6)->keep("_")->setSeparator(".");
            yield BooleanField::new('locked')->setColumns(6)->withConfirmation();

            yield BooleanField::new('secure')->setColumns(6)->renderAsSwitch(false)->onlyOnIndex();
            yield BooleanField::new('secure')->setColumns(6)->hideOnIndex()->withConfirmation();

            yield TranslationField::new("label")->renderAsHtml();
            yield TranslationField::new("help")->renderAsHtml()->setRequired(false)
                ->setFields([
                    "label" => [],
                    "class" => [],
                    "vault" => [],
                    "help" => ["form_type" => TextareaType::class]
                ])
                ->setExcludedFields("value");
        }, $args);
    }
}
