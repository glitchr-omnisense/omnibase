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

    public const OPTION_DISPLAY_LIMIT = 'displayLimit';
    public const OPTION_RELATED_URL = 'relatedUrl';
    public const OPTION_DOCTRINE_ASSOCIATION_TYPE = 'associationType';

    public const OPTION_SHOWFIRST = 'showFirst';
    public const NO_SHOW        = 0;
    public const SHOW_NAME_ONLY = 1;
    public const SHOW_ICON_ONLY = 2;
    public const SHOW_ALL       = 3;

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/entity')
            ->setFormType(AssociationType::class)
            ->addCssClass('field-entity')
            ->addCssClass('file-widget')
            ->setFormTypeOption("multiple", true)
            ->setTemplatePath('@EasyAdmin/crud/field/association.html.twig')
            ->setTextAlign(TextAlign::CENTER)
            ->setFormTypeOptionIfNotSet("class", null)
            ->setFormTypeOption("multiple", true)
            ->setCustomOption(self::OPTION_RENDER_FORMAT, "text")
            ->setCustomOption(self::OPTION_DISPLAY_LIMIT, 2)
            ->setCustomOption(self::OPTION_SHOWFIRST, false)
            ->setCustomOption(self::OPTION_CRUD_CONTROLLER, null)
            ->setCustomOption(self::OPTION_RELATED_URL, null)
            ->setCustomOption(self::OPTION_DOCTRINE_ASSOCIATION_TYPE, null);
    }

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

    public function renderAsCount(): self
    {
        $this->setCustomOption(self::OPTION_RENDER_FORMAT, "count");
        return $this;
    }

    public function setDisplayLimit(int $limit = 2): self
    {
        $this->setCustomOption(self::OPTION_DISPLAY_LIMIT, $limit);
        return $this;
    }

    public function showFirst(int $show = self::SHOW_ALL)
    {
        $this->setCustomOption(self::OPTION_SHOWFIRST, $show);
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
}
