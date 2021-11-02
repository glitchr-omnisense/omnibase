<?php

namespace Base\Field\Traits;

use Base\Field\SelectField;
use Base\Field\Type\SelectType;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use Exception;

trait SelectConfiguratorTrait
{
    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $choices = $this->getChoices($entityDto, $field);
        $icons   = $this->getIcons($field);

        $choices = $this->getFilteredChoices(
                            $choices,
                            $this->getFilter($field)
                        );

        // Formatted value
        $formattedValue = [];
        if (!is_array($field->getValue()))
            $formattedValue = $this->getFormattedValue($field->getValue(), $choices, $icons);
        else {
            foreach ($field->getValue() as $key => $value)
                $formattedValue[$key] = $this->getFormattedValue($value, $choices, $icons);
        }
        $field->setFormattedValue($formattedValue);

        // Set default value
        if ($field->getValue() == null)
            $field->setValue($this->getDefault($field));
        $field->setFormTypeOption("empty_data", $this->getDefault($field));

        if (empty($choices))
            throw new \InvalidArgumentException(sprintf('The "%s" choice field must define its possible choices using the setChoices() method.', $field->getProperty()));

        $field->setFormTypeOptionIfNotSet('choices', $this->getChoicesWithIcons($choices, $icons));
        $field->setFormTypeOptionIfNotSet('multiple', $field->getCustomOption(SelectField::OPTION_ALLOW_MULTIPLE_CHOICES));
        $field->setFormTypeOptionIfNotSet('placeholder', '');

        $fieldValue = $field->getValue();
        $isIndexOrDetail = \in_array($context->getCrud()->getCurrentPage(), [Crud::PAGE_INDEX, Crud::PAGE_DETAIL], true);
        if (null === $fieldValue || !$isIndexOrDetail) {
            return;
        }
    }

    private function getDefault(FieldDto $field)
    {
        return $field->getCustomOption(SelectField::OPTION_DEFAULT_CHOICE)
               ?? $field->getFormTypeOption("empty_data") ?? "";
    }

    private function getChoices(EntityDto $entity, FieldDto $field): array
    {
        $choiceGenerator = $field->getCustomOption(SelectField::OPTION_CHOICES)
                           ?? $field->getFormTypeOption("choices")
                           ?? $field->getFormType()::getChoices();

        if (null === $choiceGenerator) {
            return [];
        }

        if (\is_array($choiceGenerator)) {
            return $choiceGenerator;
        }

        return $choiceGenerator($entity->getInstance(), $field);
    }

    private function getIcons(FieldDto $field)
    {
        $icons = $field->getCustomOption(SelectField::OPTION_ICONS)
                ?? $field->getFormTypeOption("choice_icons")
                ?? $field->getFormType()::getIcons() ?? [];

        foreach($icons as $key => $icon) {

            if (is_object($icon) && !method_exists($icon, '__toString'))
                throw new Exception("Extra variable must be stringable..");

            if (is_string($icon)) {

                if (str_contains($icon, "/")) // Replace by image
                    $icon = "<img src=\"" . $icon . "\" loading=\"lazy\" alt=\"" . $icon . "\">";
                else if (str_starts_with($icon, "fa"))
                    $icon = "<i class=\"" . $icon . "\"></i>";
            }

            $icons[$key] = $icon;
        }

        return $icons;
    }

    private function getFilter(FieldDto $field)
    {
        return $field->getCustomOption(SelectField::OPTION_FILTER) ?? null;
    }

    function getFilteredChoices($choiceGenerator, $filter)
    {
        if($filter == null) return $choiceGenerator;

        $newChoiceGenerator = [];
        foreach ($choiceGenerator as $key => $value) {

            if (SelectType::array_associative_keys($value))
                $newChoiceGenerator[$key] = $this->getFilteredChoices($value, $filter);
            else if (in_array($value, $filter))
                $newChoiceGenerator[$key] = $value;
        }

        return $newChoiceGenerator;
    }

    function getChoicesWithIcons($choiceGenerator, $icons)
    {
        if($icons === null)
            return $choiceGenerator;

        $newChoiceGenerator = [];
        foreach ($choiceGenerator as $key => $value) {

            if (SelectType::array_associative_keys($value))
                $newChoiceGenerator[$key] = $this->getChoicesWithIcons($value, $icons);
            else {

                $icon = $icons[$value] ?? "";
                $newChoiceGenerator[trim(/*$icon." ".*/$key)] = $value; // To be reviewed and improved later.. icon is now added in SelectType
            }
        }

        return $newChoiceGenerator;
    }

    private function getFormattedValue($value, array $choiceGenerator, array $icons)
    {
        $formattedValue = [];

        $generator = array_flip(SelectType::array_flatten($choiceGenerator));
        $formattedValue[] = $generator[$value] ?? "";

        if(array_key_exists($value, $icons))
            $formattedValue[] = $icons[$value];

        return $formattedValue;
    }
}
