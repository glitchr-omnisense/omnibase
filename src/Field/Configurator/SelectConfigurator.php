<?php

namespace Base\Field\Configurator;

use Base\Field\SelectField;
use Base\Field\Type\SelectType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Exception;
use Symfony\Contracts\Translation\TranslatorInterface;

class SelectConfigurator implements FieldConfiguratorInterface
{
    public function __construct(TranslatorInterface $translator, AdminUrlGenerator $adminUrlGenerator)
    {
        $this->translator = $translator;
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return SelectField::class === $field->getFieldFqcn();
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        // Formatted value
        $class = $field->getCustomOption(SelectField::OPTION_CLASS);

        $values = $field->getValue();
        $values = is_array($values) ? new ArrayCollection($values) : $values;

        $formattedValues = [];
        if (!$values instanceof Collection) {

            $value = $field->getValue();
            $field->setCustomOption(SelectField::OPTION_RENDER_AS_COUNT, false);
            
            $dataClass = $class ?? (is_object($value) ? get_class($value) : null);
            $formattedValues = SelectType::getFormattedValues($field->getValue(), $dataClass, $this->translator);

        } else {

            foreach ($values as $key => $value) {

                $dataClass = $class ?? (is_object($value) ? get_class($value) : null);
                $formattedValues[$key] = SelectType::getFormattedValues($value, $dataClass, $this->translator);
            }
        }

        $field->setFormattedValue(!empty($formattedValues) ? $formattedValues : null);

        // Set default value
        if ($field->getValue() == null)
            $field->setValue($this->getDefault($field));

        $field->setFormTypeOption("empty_data", $this->getDefault($field));

        $field->setFormTypeOptionIfNotSet('choice_filters', $field->getCustomOption(SelectField::OPTION_FILTER) ?? null);
        $field->setFormTypeOptionIfNotSet('autocomplete', $field->getCustomOption(SelectField::OPTION_AUTOCOMPLETE));
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
}
