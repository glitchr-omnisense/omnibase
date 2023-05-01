<?php

namespace Base\Field\Configurator;

use Base\Field\IconField;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 *
 */
class IconConfigurator extends SelectConfigurator
{
    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return IconField::class === $field->getFieldFqcn();
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        $icon = null;
        if (null !== $field->getCustomOption(IconField::OPTION_TARGET_FIELD_NAME)) {
            $icon = $propertyAccessor->getValue($entityDto->getInstance(), $field->getCustomOption(IconField::OPTION_TARGET_FIELD_NAME));
        }

        $field->setCustomOption("iconColor", $icon);
    }
}
