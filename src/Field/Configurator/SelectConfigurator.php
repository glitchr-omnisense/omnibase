<?php

namespace Base\Field\Configurator;

use Base\Controller\Backend\AbstractCrudController;
use Base\Database\Mapping\ClassMetadataManipulator;
use Base\Field\SelectField;
use Base\Service\Model\Autocomplete;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Contracts\Translation\TranslatorInterface;

class SelectConfigurator implements FieldConfiguratorInterface
{
    /**
     * @var AdminUrlGenerator
     */
    protected $adminUrlGenerator;

    /**
     * @var ClassMetadataManipulator
     */
    protected $classMetadataManipulator;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var Autocomplete
     */
    protected $autocomplete;

    public function __construct(ClassMetadataManipulator $classMetadataManipulator, TranslatorInterface $translator, AdminUrlGenerator $adminUrlGenerator)
    {
        $this->translator = $translator;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->classMetadataManipulator = $classMetadataManipulator;

        $this->autocomplete = new Autocomplete($this->translator);
    }

    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return SelectField::class === $field->getFieldFqcn();
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        // Formatted value
        $class = $field->getCustomOption(SelectField::OPTION_CLASS);
        if (!$class) {
            $values = $field->getValue();
            if ($values instanceof Collection) {
                $class = is_object($values->first()) ? get_class($values->first()) : null;
            }
        }

        $values = $field->getValue();
        $values = is_array($values) ? new ArrayCollection($values) : $values;
        $formattedValues = [];

        $defaultClass = $this->classMetadataManipulator->getTargetClass($entityDto->getFqcn(), $field->getProperty());
        if ($this->classMetadataManipulator->isEntity($entityDto->getFqcn()) && $this->classMetadataManipulator->isToManySide($entityDto->getFqcn(), $field->getProperty())) {
            $field->setSortable(false);
        }

        $showFirst = $field->getCustomOption(SelectField::OPTION_SHOW_FIRST);
        $displayLimit = $field->getCustomOption(SelectField::OPTION_DISPLAY_LIMIT);
        if ($showFirst) {
            $displayLimit--;
        }

        if ($values instanceof Collection) {
            foreach ($values as $key => $value) {
                $dataClass = $class ?? (is_object($value) ? get_class($value) : null);
                $dataClass = $dataClass ?? $defaultClass;

                if ($key > $displayLimit) {
                    $formattedValues[$key] = $value;
                } else {
                    $formattedValues[$key] = $this->autocomplete->resolve($value, $dataClass);

                    $dataClassCrudController = AbstractCrudController::getCrudControllerFqcn($dataClass);
                    if ($formattedValues[$key] && $dataClassCrudController) {
                        $formattedValues[$key]["url"] = $this->adminUrlGenerator->setController($dataClassCrudController)->setEntityId($value->getId())->setAction(Action::DETAIL)->generateUrl();
                    }
                }
            }
        } else {
            $value = $field->getValue();

            $field->setCustomOption(SelectField::OPTION_RENDER_FORMAT, "text");

            $dataClass = $class ?? (is_object($value) ? get_class($value) : null);
            $dataClass = $dataClass ?? $defaultClass;

            $dataClassCrudController = AbstractCrudController::getCrudControllerFqcn($dataClass);
            $formattedValues = $this->autocomplete->resolve($field->getValue(), $dataClass);
            if ($formattedValues && $dataClassCrudController) {
                $formattedValues["url"] = $this->adminUrlGenerator->setController($dataClassCrudController)->setEntityId($value->getId())->setAction(Action::DETAIL)->generateUrl();
            }
        }

        $field->setFormattedValue($formattedValues);
        $fieldValue = $field->getValue();

        $isIndexOrDetail = \in_array($context->getCrud()->getCurrentPage(), [Crud::PAGE_INDEX, Crud::PAGE_DETAIL], true);
        if (null === $fieldValue || !$isIndexOrDetail) {
            return;
        }
    }
}
