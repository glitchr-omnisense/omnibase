<?php

namespace Base\Field;

use Base\Field\Type\AvatarType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;

use Base\Field\Type\EntityType;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;

final class EntityField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_RENDER_FORMAT   = "renderFormat";

    public const OPTION_DROPZONE = 'dropzone';
    public const OPTION_DROPZONE_LABEL = 'dropzone_label';

    public const OPTION_ALLOW_ADD = 'allowAdd';
    public const OPTION_ALLOW_DELETE = 'allowDelete';
    public const OPTION_ENTRY_IS_COMPLEX = 'entryIsComplex';
    public const OPTION_ENTRY_TYPE = 'entryType';
    public const OPTION_SHOW_ENTRY_LABEL = 'showEntryLabel';

    public const OPTION_AUTOLOAD = "autoload";

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/entity')
            ->setFormType(EntityType::class)
            ->addCssClass('field-entity')
            ->addCssClass('file-widget')
            ->setFormTypeOption("multiple", true)
            ->setTemplatePath('@EasyAdmin/crud/field/entity.html.twig')
            ->setTextAlign(TextAlign::CENTER)
            ->setFormTypeOptionIfNotSet("class", null)
            ->setCustomOption(self::OPTION_ENTRY_TYPE, null)
            ->setCustomOption(self::OPTION_SHOW_ENTRY_LABEL, false)
            ->setCustomOption(self::OPTION_RENDER_FORMAT, "text");
    }

    public function allowDelete(bool $allowDelete = true): self
    {
        $this->setFormTypeOption("allow_delete", $allowDelete);
        return $this;
    }

    public function autoload($autoload = true): self
    {
        $this->setFormTypeOption(self::OPTION_AUTOLOAD, $autoload);
        return $this;
    }

    public function renderAsList(): self
    {
        $this->setCustomOption(self::OPTION_RENDER_FORMAT, "text");
        return $this;
    }

    public function renderAsSelect2(): self
    {
        $this->setCustomOption(self::OPTION_RENDER_FORMAT, "select2");
        return $this;
    }

    public function setFields(array $fields): self
    {
        $this->setFormTypeOption("fields", $fields);
        return $this;
    }


    public function setClass($dataClass): self
    {
        $this->setFormTypeOption("class", $dataClass);
        return $this;
    }

    public function renderAsDropzone(string $uploaderProperty, ?string $labelProperty = null): self
    {
        $this->setCustomOption(self::OPTION_RENDER_FORMAT, "dropzone");
        $this->setCustomOption(self::OPTION_DROPZONE, $uploaderProperty);
        $this->setCustomOption(self::OPTION_DROPZONE_LABEL, $labelProperty);
        return $this;
    }
}
