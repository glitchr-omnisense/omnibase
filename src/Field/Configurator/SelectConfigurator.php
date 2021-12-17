<?php

namespace Base\Field\Configurator;

use Base\Controller\Dashboard\AbstractCrudController;
use Base\Database\Factory\ClassMetadataManipulator;
use Base\Field\SelectField;
use Base\Field\Type\SelectType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
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
    public function __construct(ClassMetadataManipulator $classMetadataManipulator, TranslatorInterface $translator, AdminUrlGenerator $adminUrlGenerator)
    {
        $this->translator = $translator;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->classMetadataManipulator = $classMetadataManipulator;
    }

    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return SelectField::class === $field->getFieldFqcn();
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        // Formatted value
        $class = $field->getCustomOption(SelectField::OPTION_CLASS);
        if(!$class) {

            $values = $field->getValue();
            if($values instanceof Collection) 
                $class = is_object($values->first()) ? get_class($values->first()) : null;
        }
        
        $values = $field->getValue();
        $values = is_array($values) ? new ArrayCollection($values) : $values;
        $formattedValues = [];

        if ($values instanceof Collection) {

            foreach ($values as $key => $value) {

                $dataClass = $class ?? (is_object($value) ? get_class($value) : null);
                $dataClassCrudController = AbstractCrudController::getCrudControllerFqcn($dataClass);
                
                $formattedValues[$key] = SelectType::getFormattedValues($value, $dataClass, $this->translator);
                if ($formattedValues[$key] && $dataClassCrudController)
                    $formattedValues[$key]["url"] = $this->adminUrlGenerator->setController($dataClassCrudController)->setEntityId($value->getId())->setAction(Action::DETAIL)->generateUrl();

            }

        } else {

            $value = $field->getValue();
            $field->setCustomOption(SelectField::OPTION_RENDER_AS_COUNT, false);
            
            $dataClass = $class ?? (is_object($value) ? get_class($value) : null);
            $dataClassCrudController = AbstractCrudController::getCrudControllerFqcn($dataClass);

            $formattedValues = SelectType::getFormattedValues($field->getValue(), $dataClass, $this->translator);
            if ($formattedValues && $dataClassCrudController)
                $formattedValues["url"] = $this->adminUrlGenerator->setController($dataClassCrudController)->setEntityId($value->getId())->setAction(Action::DETAIL)->generateUrl();
        }

        $field->setFormattedValue(!empty($formattedValues) ? $formattedValues : null);

        // Set default value
        if ($field->getValue() == null)
            $field->setValue($this->getDefault($field));

        $field->setFormTypeOption("empty_data", $this->getDefault($field));
        $field->setFormTypeOptionIfNotSet('choice_filter', $field->getCustomOption(SelectField::OPTION_FILTER) ?? null);
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
