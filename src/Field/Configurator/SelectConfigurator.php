<?php

namespace Base\Field\Configurator;

use Base\Field\SelectField;
use Base\Field\Traits\SelectConfiguratorTrait;
use Base\Field\Type\SelectType;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use Exception;

class SelectConfigurator implements FieldConfiguratorInterface
{
    use SelectConfiguratorTrait;

    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return SelectField::class === $field->getFieldFqcn();
    }
}
