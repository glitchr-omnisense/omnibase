<?php

namespace Base\Field\Configurator;

use Base\Annotations\Annotation\Uploader;
use Base\Field\CropperField;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;

/**
 *
 */
class CropperConfigurator implements FieldConfiguratorInterface
{
    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return CropperField::class === $field->getFieldFqcn();
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $file = Uploader::getPublic($entityDto->getInstance(), $field->getProperty()) ?? "";
        $field->setFormattedValue($file);
    }
}
