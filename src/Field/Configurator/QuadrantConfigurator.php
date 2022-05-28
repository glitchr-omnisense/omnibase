<?php

namespace Base\Field\Configurator;

use Base\Annotations\Annotation\Uploader;
use Base\Field\QuadrantField;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;

class QuadrantConfigurator extends SelectConfigurator
{
    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return QuadrantField::class === $field->getFieldFqcn();
    }
}
