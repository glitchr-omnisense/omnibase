<?php

namespace Base\Field\Configurator;

use Base\Field\ForexField;
use Base\Field\IconField;
use Base\Field\Type\IconType;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use Symfony\Component\PropertyAccess\PropertyAccess;

class IconConfigurator extends SelectConfigurator
{
    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return ForexField::class === $field->getFieldFqcn();
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
    }
}
