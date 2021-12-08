<?php

namespace Base\Field\Configurator;

use Base\Field\RoleField;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;

class RoleConfigurator extends SelectConfigurator
{
    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return RoleField::class === $field->getFieldFqcn();
    }
}
