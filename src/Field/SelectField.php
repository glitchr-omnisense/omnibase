<?php

namespace Base\Field;

use Base\Field\Type\SelectType;

use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;

final class SelectField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_ALLOW_MULTIPLE_CHOICES = 'allowMultipleChoices';
    public const OPTION_AUTOCOMPLETE = 'autocomplete';
    public const OPTION_CHOICES = 'choices';
    public const OPTION_ICONS = 'icons';
    public const OPTION_FILTER = 'filter';
    public const OPTION_DEFAULT_CHOICE = "default_choice";

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/text')
            ->setFormType(SelectType::class)
            ->setTemplatePath('@EasyAdmin/crud/field/select.html.twig')
            ->addCssClass('field-select');
    }

    public function allowMultipleChoices(bool $allow = true): self
    {
        $this->setCustomOption(self::OPTION_ALLOW_MULTIPLE_CHOICES, $allow);
        return $this;
    }

    public function setChoices($choiceGenerator): self
    {
        if (!\is_array($choiceGenerator) && !\is_callable($choiceGenerator))
            throw new \InvalidArgumentException(sprintf('The argument of the "%s" method must be an array or a closure ("%s" given).', __METHOD__, \gettype($choiceGenerator)));

        $this->setCustomOption(self::OPTION_CHOICES, $choiceGenerator);
        return $this;
    }

    public function setFilter(array $filter)
    {
        $this->setCustomOption(self::OPTION_FILTER, $filter);
        return $this;
    }

    public function setIcons(array $icons)
    {
        $this->setCustomOption(self::OPTION_ICONS, $icons);
        return $this;
    }

    public function setDefault($defaultChoices)
    {
        if(!is_array($defaultChoices))
            $defaultChoices = [$defaultChoices];

        $this->setCustomOption(self::OPTION_DEFAULT_CHOICE, $defaultChoices);
        return $this;
    }
}