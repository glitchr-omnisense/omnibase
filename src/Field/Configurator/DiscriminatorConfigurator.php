<?php

namespace Base\Field\Configurator;

use Base\Controller\Backend\AbstractCrudController;
use Base\Database\Mapping\ClassMetadataManipulator;
use Base\Field\DiscriminatorField;
use Base\Field\Type\DiscriminatorType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Contracts\Translation\TranslatorInterface;

class DiscriminatorConfigurator implements FieldConfiguratorInterface
{
    /**
     * @var AdminUrlGenerator
     */
    private $adminUrlGenerator;

    /**
     * @var ClassMetadataManipulator
     */
    protected $classMetadataManipulator;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(ClassMetadataManipulator $classMetadataManipulator, TranslatorInterface $translator, AdminUrlGenerator $adminUrlGenerator)
    {
        $this->translator = $translator;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->classMetadataManipulator = $classMetadataManipulator;
    }

    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return DiscriminatorField::class === $field->getFieldFqcn();
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $showColumn = $field->getCustomOption(DiscriminatorField::OPTION_SHOW_COLUMN);
        $showInline = $field->getCustomOption(DiscriminatorField::OPTION_SHOW_INLINE);
        $showLast   = $field->getCustomOption(DiscriminatorField::OPTION_SHOW_LEAF);

        $discriminatorAutoload = $field->getCustomOption(DiscriminatorField::OPTION_DISCRIMINATOR_AUTOLOAD);
        if ($discriminatorAutoload) {
            $field->setFormType(DiscriminatorType::class);
        }

        $defaultClass = $field->getValue() && class_exists($field->getValue()) ? $field->getValue() : null;
        $defaultClass = $defaultClass ?? ($entityDto->getInstance() ? get_class($entityDto->getInstance()) : null);
        if ($showColumn) {
            $field->setLabel(ucfirst($this->classMetadataManipulator->getDiscriminatorColumn($defaultClass)));
        }

        $discriminatorMap    = $this->classMetadataManipulator->getDiscriminatorMap($entityDto->getInstance());
        $discriminatorValue  = $this->classMetadataManipulator->getDiscriminatorValue($entityDto->getInstance());
        $classCrudController = AbstractCrudController::getCrudControllerFqcn($entityDto->getInstance());

        $field->setValue($discriminatorValue);
        $formattedValues = [];
        $array   = explode("_", $field->getValue());
        foreach ($array as $key => $value) {
            $value = implode("_", array_slice($array, 0, $key+1));
            $text  = implode(".", array_slice($array, 0, $key+1));

            $class = $discriminatorMap[$value] ?? null;
            if ($key == array_key_last($array)) {
                $class = $class ?? $defaultClass;
            }

            if ($class) {
                $class = str_replace(["App\\", "Base\\Entity\\"], ["Base\\", ""], $class);
                $text  = implode(".", array_map("camel2snake", explode("\\", $class)));
            }

            if ($class) {
                $formattedValues[] = DiscriminatorType::getFormattedValues($text, $discriminatorMap[$value] ?? $defaultClass, $this->translator);
            }
        }

        if ($showLast) {
            $formattedValues = [end($formattedValues)];
        } elseif ($showInline) {
            $lastEntry = end($formattedValues);
            $label = array_map(fn ($v) => $v["text"], $formattedValues);
            $lastEntry["text"] = implode(" > ", $label);
            $formattedValues = [$lastEntry];
        }

        if (!empty($formattedValues) && $classCrudController) {
            $formattedValues[count($formattedValues)-1]["url"] = $this->adminUrlGenerator->unsetAll()->setController($classCrudController)->setAction(Action::INDEX)->set("filters[".$field->getProperty()."][value]", $discriminatorValue)->generateUrl();
        }

        $field->setValue(array_transforms(fn ($k, $v): array => [$k, $v["id"] ?? null], $formattedValues));
        $field->setFormattedValue($formattedValues);
    }
}
