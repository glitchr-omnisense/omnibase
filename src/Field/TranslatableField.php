<?php

namespace Base\Field;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;

class TranslatableField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(TranslatableType::class)
            ->setFormTypeOptions(["default_locale" => "%locale%"]);
    }

    public function setFields(array $fields): self
    {
        $this->setFormTypeOption("fields", $fields);
        return $this;
    }

    public function setExcludedFields(array $excludedFields): self
    {
        $this->setFormTypeOption("excluded_fields", $excludedFields);
        return $this;
    }

    public function setDefaultLocale(string $defaultLocale): self
    {
        $this->setFormTypeOption("default_locale", $defaultLocale);
        return $this;
    }
}