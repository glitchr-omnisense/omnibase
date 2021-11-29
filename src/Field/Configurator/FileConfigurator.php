<?php

namespace Base\Field\Configurator;

use Base\Annotations\Annotation\Uploader;
use Base\Database\Factory\ClassMetadataManipulator;
use Base\Field\FileField;
use Base\Service\BaseService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;

final class FileConfigurator implements FieldConfiguratorInterface
{
    public function __construct(ClassMetadataManipulator $classMetadataManipulator)
    {
        $this->classMetadataManipulator = $classMetadataManipulator;
    }

    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return FileField::class === $field->getFieldFqcn();
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $field->setFormTypeOption("max_filesize", Uploader::getMaxFilesize($entityDto->getInstance(), $field->getProperty()));
        $field->setFormTypeOption("mime_types",   Uploader::getMimeTypes($entityDto->getInstance(), $field->getProperty()));

        $preferredDownloadName = $field->getCustomOption(FileField::OPTION_PREFERRED_DOWNLOAD_NAME);
        if($preferredDownloadName) {
        
            $entity = $entityDto->getInstance();
            $classMetadata = $this->classMetadataManipulator->getClassMetadata($entity);
            if($classMetadata->hasField($preferredDownloadName)) 
                $preferredDownloadName = $classMetadata->getFieldValue($entity, $preferredDownloadName);

            $field->setCustomOption(FileField::OPTION_PREFERRED_DOWNLOAD_NAME, $preferredDownloadName);
        }

        $file = Uploader::getPublicPath($entityDto->getInstance(), $field->getProperty()) ?? "";
        $field->setFormattedValue($file);
    }
}
