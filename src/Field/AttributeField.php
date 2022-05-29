<?php

namespace Base\Field;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Base\Field\Type\AttributeType;

use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;

final class AttributeField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_CLASS   = 'class';
    public const OPTION_CHOICES = 'choices';
    public const OPTION_ICONS   = 'icons';
    public const OPTION_FILTER  = 'filter';

    public const OPTION_DISPLAY_LIMIT = 'displayLimit';
    public const OPTION_ICON_ALIGN    = 'iconAlign';

    public const OPTION_RENDER_FORMAT   = "renderFormat";
    public const OPTION_SHOW_FIRST      = 'showFirst';
    public const OPTION_SHOW            = 'show';

    public const NO_SHOW        = 0;
    public const SHOW_NAME_ONLY = 1;
    public const SHOW_ICON_ONLY = 2;
    public const SHOW_ALL       = 3;

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/text')
            ->setTemplatePath('@EasyAdmin/crud/field/attribute.html.twig')
            ->setFormType(AttributeType::class)
            ->setCustomOption(self::OPTION_DISPLAY_LIMIT, 2)
            ->setCustomOption(self::OPTION_SHOW, self::SHOW_ICON_ONLY)
            ->addCssClass('field-text');
    }

    public const OPTION_FILTER_CODE  = 'filter_code';
    public function setFilterCode(?string $filter = null): self
    {
        $this->setFormTypeOption(self::OPTION_FILTER_CODE, $filter);
        return $this;
    }

    public function setClass(?string $class = null)
    {
        $this->setFormTypeOption(self::OPTION_CLASS, $class);
        return $this;
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

    public function setFields(array $fields): self
    {
        $this->setFormTypeOption("fields", $fields);
        return $this;
    }

    public function setTextAlign(string $textAlign)
    {
        $this->setIconAlign($textAlign);
        $this->dto->setTextAlign($textAlign);
        return $this;
    }

    public function setIconAlign(string $iconAlign)
    {
        $this->setCustomOption(self::OPTION_ICON_ALIGN, $iconAlign);
        return $this;
    }

    public function allowMultipleChoices(bool $allow = true)
    {
        $this->setFormTypeOption("multiple", $allow);
        return $this;
    }
    public function allowMultiValues(bool $allow = true)
    {
        $this->setFormTypeOption("multivalue", $allow);
        return $this;
    }

    public function setChoices($choiceGenerator)
    {
        if (!\is_array($choiceGenerator) && !\is_callable($choiceGenerator))
            throw new \InvalidArgumentException(sprintf('The argument of the "%s" method must be an array or a closure ("%s" given).', __METHOD__, \gettype($choiceGenerator)));

        $this->setCustomOption(self::OPTION_CHOICES, $choiceGenerator);
        return $this;
    }

    public function setFilter(...$filter)
    {
        $this->setFormTypeOptionIfNotSet(self::OPTION_FILTER, $filter);
        return $this;
    }

    public function setDisplayLimit(int $limit = 2)
    {
        $this->setCustomOption(self::OPTION_DISPLAY_LIMIT, $limit);
        return $this;
    }

    public function setIcons(array $icons)
    {
        $this->setCustomOption(self::OPTION_ICONS, $icons);
        return $this;
    }

    public function showFirst(int $show = self::SHOW_ALL)
    {
        $this->setCustomOption(self::OPTION_SHOW_FIRST, $show);
        return $this;
    }

    public function show(int $show = self::SHOW_ALL)
    {
        $this->setCustomOption(self::OPTION_SHOW, $show);
        return $this;
    }

    public function renderAsCount()
    {
        $this->setCustomOption(self::OPTION_RENDER_FORMAT, "count");
        return $this;
    }
}
