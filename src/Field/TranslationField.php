<?php

namespace Base\Field;

use Base\Field\Type\TranslationType;
use Base\Service\LocaleProvider;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;

class TranslationField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName = null, ?string $label = null): self
    {
        $field = (new self())
            ->setProperty("translations")
            ->hideOnIndex()
            ->setTemplateName('crud/field/text')
            ->setTemplatePath('@EasyAdmin/crud/field/translatable.html.twig')
            ->setCustomOption("required", true)
            ->setFormType(TranslationType::class);

        if($propertyName) $field->setFields([$propertyName => []])->showOnIndex($propertyName);
        else $field->hideOnDetail();

        return $field;
    }

    public function setFields(array $fields): self
    {
        $this->setFormTypeOption("fields", $fields);
        return $this;
    }

    public function showOnIndex(?string $field): self
    {
        if($field) {

            $this->setCustomOption("show_field", $field);
            
            $displayedOn = $this->dto->getDisplayedOn();
            $displayedOn->set(Crud::PAGE_INDEX, Crud::PAGE_INDEX);
            $this->dto->setDisplayedOn($displayedOn);
        }

        return $this;
    }

    public function setRequired(bool $required = true)
    {
        $this->setCustomOption("required", $required);
        return $this;
    }

    public function setExcludedFields($excludedFields): self
    {
        if(!is_array($excludedFields)) $excludedFields = [$excludedFields];
        $this->setFormTypeOption("excluded_fields", $excludedFields);
        return $this;
    }
    public function onlySetFields($onlyFields): self
    {
        if(!is_array($onlyFields)) $onlyFields = [$onlyFields];
        $this->setFormTypeOption("only_fields", $onlyFields);
        return $this;
    }
    public function setTranslationClass($translationClass): self
    {
        $this->setFormTypeOption("translation_class", $translationClass);
        return $this;
    }

    public function setDefaultLocale(string $defaultLocale): self
    {
        $this->setFormTypeOption("default_locale", $defaultLocale);
        return $this;
    }
    public function renderSingleLocale(?string $singleLocale = null): self
    {
        $singleLocale = $singleLocale ?? LocaleProvider::getDefaultLocale();
        $this->setFormTypeOption("single_locale", $singleLocale);
        return $this;
    }
}