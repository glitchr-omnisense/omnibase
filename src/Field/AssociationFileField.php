<?php

namespace Base\Field;

use Base\Field\Type\AssociationFileType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;

use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;

final class AssociationFileField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_SHOWFIRST = "showFirst";

    public const OPTION_RENDER_FORMAT   = "renderFormat";
    public const OPTION_CRUD_CONTROLLER = 'crudControllerFqcn';

    public const OPTION_ALLOW_ADD = 'allowAdd';
    public const OPTION_ALLOW_DELETE = 'allowDelete';

    public const OPTION_RELATED_URL = 'relatedUrl';
    public const OPTION_DOCTRINE_ASSOCIATION_TYPE = 'associationType';
    public const OPTION_CLASS          = 'class';

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/entity')
            ->setFormType(AssociationFileType::class)
            ->addCssClass('field-entity')
            ->addCssClass('file-widget')
            ->setTemplatePath('@EasyAdmin/crud/field/association.html.twig')
            ->setTextAlign(TextAlign::CENTER)
            ->setFormTypeOptionIfNotSet("class", null)
            ->setCustomOption(self::OPTION_RENDER_FORMAT, "count");
    }

    public function allowDelete(bool $allowDelete = true): self
    {
        $this->setFormTypeOption("allow_delete", $allowDelete);
        return $this;
    }

    public function setMultipleFiles(bool $multipleFiles = true): self
    {
        $this->setFormTypeOption("multiple", $multipleFiles);
        return $this;
    }

    public function setClass(?string $class = null)
    {
        $this->setFormTypeOption(self::OPTION_CLASS, $class);
        return $this;
    }

    public function renderAsText(): self
    {
        $this->setCustomOption(self::OPTION_RENDER_FORMAT, "text");
        return $this;
    }

    public function renderAsCount(): self
    {
        $this->setCustomOption(self::OPTION_RENDER_FORMAT, "count");
        return $this;
    }

    public function renderAsImage(): self
    {
        $this->setCustomOption(self::OPTION_RENDER_FORMAT, "image")
             ->setFormType(ImageType::class);
        return $this;
    }

    public function showFirst(bool $show = true): self
    {
        $this->setCustomOption(self::OPTION_SHOWFIRST, true);
        return $this;
    }

    public function setFieldFile(string $fieldFile): self
    {
        $this->setFormTypeOption("entity_file", $fieldFile);
        return $this;
    }

    public function setFieldValues(array|callable $fieldValues): self
    {
        $this->setFormTypeOption("entity_values", $fieldValues);
        return $this;
    }
}
