<?php

namespace Base\Field\Configurator;

use Base\Field\StateField;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;

/**
 *
 */
class StateConfigurator extends SelectConfigurator
{
    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return StateField::class === $field->getFieldFqcn();
    }
}
