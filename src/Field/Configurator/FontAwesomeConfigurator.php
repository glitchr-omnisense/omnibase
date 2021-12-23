<?php

namespace Base\Field\Configurator;

use Base\Field\FontAwesomeField;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use Symfony\Component\PropertyAccess\PropertyAccess;

class FontAwesomeConfigurator extends SelectConfigurator
{
    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return FontAwesomeField::class === $field->getFieldFqcn();
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        $icon = null;
        if( null !== $field->getCustomOption(FontAwesomeField::OPTION_TARGET_FIELD_NAME))
            $icon = $propertyAccessor->getValue($entityDto->getInstance(), $field->getCustomOption(FontAwesomeField::OPTION_TARGET_FIELD_NAME));

        $field->setCustomOption("iconColor", $icon);
    }
}
