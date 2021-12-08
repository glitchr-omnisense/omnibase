<?php

namespace Base\Field\Configurator;

use Base\Field\FontAwesomeField;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;

class FontAwesomeConfigurator extends SelectConfigurator
{
    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return FontAwesomeField::class === $field->getFieldFqcn();
    }
}
