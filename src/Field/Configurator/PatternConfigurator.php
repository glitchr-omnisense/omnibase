<?php

namespace Base\Field\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;

use Symfony\Contracts\Translation\TranslatorInterface;

use Base\Field\PatternField;

class PatternConfigurator implements FieldConfiguratorInterface
{
    private $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return PatternField::class === $field->getFieldFqcn();
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        if (null === $patternFieldName = $field->getCustomOption(PatternField::OPTION_PATTERN_FIELD_NAME)) {
            throw new \RuntimeException(sprintf('The "%s" field must define the name of the field whose contents are used for the pattern using the "setPatternFieldName()" method.', $field->getProperty()));
        }
        
        if($patternFieldName) {
        
            $entity = $entityDto->getInstance();
            $classMetadata = $this->classMetadataManipulator->getClassMetadata($entity);
            if($classMetadata->hasField($patternFieldName)) 
                $pattern = $classMetadata->getFieldValue($entity, $patternFieldName);
        }

        $field->setFormTypeOption('pattern', $pattern ?? null);
    }
}
