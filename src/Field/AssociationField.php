<?php

namespace Base\Field;

use Base\Database\Factory\ClassMetadataManipulator;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;

use Base\Field\Type\AssociationType;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;

final class AssociationField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_RENDER_FORMAT   = "renderFormat";
    public const OPTION_CRUD_CONTROLLER = 'crudControllerFqcn';

    public const OPTION_ALLOW_ADD = 'allowAdd';
    public const OPTION_ALLOW_DELETE = 'allowDelete';

    public const OPTION_RELATED_URL = 'relatedUrl';
    public const OPTION_DOCTRINE_ASSOCIATION_TYPE = 'associationType';

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/entity')
            ->setFormType(AssociationType::class)
            ->addCssClass('field-entity')
            ->addCssClass('file-widget')
            ->setTemplatePath('@EasyAdmin/crud/field/association.html.twig')
            ->setTextAlign(TextAlign::CENTER)
            ->setFormTypeOptionIfNotSet("class", null)
            ->setCustomOption(self::OPTION_RENDER_FORMAT, "count")
            ->setCustomOption(self::OPTION_CRUD_CONTROLLER, null)
            ->setCustomOption(self::OPTION_RELATED_URL, null)
            ->setCustomOption(self::OPTION_DOCTRINE_ASSOCIATION_TYPE, null);
    }

    public function justDisplay(): self { return $this->allowDelete(false)->allowAdd(false)->autoload(false); }

    public function allowDelete(bool $allowDelete = true): self
    {
        $this->setFormTypeOption("allow_delete", $allowDelete);
        return $this;
    }

    public function allowAdd(bool $allowAdd = true): self
    {
        $this->setFormTypeOption("allow_add", $allowAdd);
        return $this;
    }

    public function autoload($autoload = true): self
    {
        $this->setFormTypeOption("autoload", $autoload);
        return $this;
    }

    public function setFields(array $fields): self
    {
        $this->setFormTypeOption("fields", $fields);
        return $this;
    }
}
