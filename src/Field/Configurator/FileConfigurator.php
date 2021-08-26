<?php

namespace Base\Field\Configurator;

use Base\Database\Annotation\Uploader;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use function Symfony\Component\String\u;

use Base\Field\FileField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\Form\Extension\Core\Type\FileType;

final class FileConfigurator implements FieldConfiguratorInterface
{
    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return FileField::class === $field->getFieldFqcn();
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $file = Uploader::getFile($entityDto->getInstance(), $field->getProperty());

        // 'allow_delete' => true,
        
        // this check is needed to avoid displaying broken Files when File properties are optional
        if (empty($formattedValue) || $formattedValue === rtrim($configuredBasePath ?? '', '/')) {
            $field->setTemplateName('label/empty');
        }

        if (!\in_array($context->getCrud()->getCurrentPage(), [Crud::PAGE_EDIT, Crud::PAGE_NEW])) {
            return;
        }
    }
}
