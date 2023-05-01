<?php

namespace Base\Field\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;

use Base\Field\AttributeField;

/**
 *
 */
class AttributeConfigurator extends SelectConfigurator implements FieldConfiguratorInterface
{
    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return AttributeField::class === $field->getFieldFqcn();
    }
}
