<?php

namespace Base\Field\Configurator;

use Base\Field\DiscriminatorField;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;

class DiscriminatorConfigurator extends SelectConfigurator
{
    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return DiscriminatorField::class === $field->getFieldFqcn();
    }
}
