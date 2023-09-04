<?php

namespace Base\Field\Configurator;

use Base\Database\Mapping\ClassMetadataManipulator;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use Base\Field\TranslationField;
use Base\Service\LocalizerInterface;
use Doctrine\ORM\PersistentCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use Exception;
use Symfony\Component\PropertyAccess\PropertyAccess;

use function Symfony\Component\String\u;
use const ENT_NOQUOTES;
use const PHP_INT_MAX;

/**
 *
 */
class TranslationConfigurator implements FieldConfiguratorInterface
{
    /**
     * @var ClassMetadataManipulator
     */
    protected $classMetadataManipulator;

    /**
     * @var LocalizerInterface
     */
    private $localizer;

    /**
     * @var PropertyAccessor
     */
    private $propertyAccessor;

    public function __construct(ClassMetadataManipulator $classMetadataManipulator, LocalizerInterface $localizer)
    {
        $this->classMetadataManipulator = $classMetadataManipulator;
        $this->localizer = $localizer;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return TranslationField::class === $field->getFieldFqcn();
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $required = $field->getCustomOption("required");
        $field->setFormTypeOption("required", $required);
        $field->setSortable(false);

        // Show formatted value
        if (($fieldName = $field->getCustomOption("show_field"))) {
            if ($entityDto->getInstance() && !PropertyAccess::createPropertyAccessor()->isReadable($entityDto->getInstance(), $field->getProperty())) {
                throw new Exception("Failed to access \"$fieldName\" in \"" . $entityDto->getName() . "\".");
            }

            $field->setLabel($field->getLabel() == mb_ucfirst($fieldName) ? $field->getLabel() : mb_ucfirst($fieldName));
            $field->setFormattedValue("-");

            $childField = $field->getValue();
            if ($childField instanceof PersistentCollection) {
                $typeClass = $childField->getTypeClass()->getName();
                if ($this->classMetadataManipulator->hasField($typeClass, $fieldName)) {
                    
                    $entity = $childField->get($this->localizer->getLocale());
                    if(!$entity) {
                        $entity ??= $childField->get(first($childField->getKeys()) ?? $this->localizer->getDefaultLocale());
                    }

                    $value = ($entity && $this->propertyAccessor->isReadable($entity, $fieldName) ? $this->propertyAccessor->getValue($entity, $fieldName) : null);
                    $renderAsHtml = $field->getCustomOption(TranslationField::OPTION_RENDER_AS_HTML);
                    $stripTags = $field->getCustomOption(TranslationField::OPTION_STRIP_TAGS);
                    if ($renderAsHtml) {
                        $formattedValue = (string)$value;
                    } elseif ($stripTags) {
                        $formattedValue = strip_tags((string)$value);
                    } else {
                        $formattedValue = htmlspecialchars((string)$value, ENT_NOQUOTES, null, false);
                    }

                    $configuredMaxLength = $field->getCustomOption(TranslationField::OPTION_MAX_LENGTH);
                    // when contents are rendered as HTML, "max length" option is ignored to prevent
                    // truncating contents in the middle of an HTML tag, which messes the entire backend
                    if (!$renderAsHtml && null !== $configuredMaxLength) {
                        $isDetailAction = Action::DETAIL === $context->getCrud()->getCurrentAction();
                        $defaultMaxLength = $isDetailAction ? PHP_INT_MAX : 64;
                        $formattedValue = u($formattedValue)->truncate($configuredMaxLength ?? $defaultMaxLength, '…')->toString();
                    }

                    $field->setFormattedValue(empty($formattedValue) ? null : $formattedValue);
                }
            }
        }
    }
}
