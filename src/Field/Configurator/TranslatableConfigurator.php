<?php

namespace Base\Field\Configurator;

use Base\Service\BaseService;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use Base\Field\TranslatableField;
use Base\Service\LocaleProvider;
use Base\Service\LocaleProviderInterface;
use Doctrine\ORM\PersistentCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class TranslatableConfigurator implements FieldConfiguratorInterface
{
    public function __construct(LocaleProviderInterface $localeProvider)
    {
        $this->localeProvider = $localeProvider;
    }
    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return TranslatableField::class === $field->getFieldFqcn();
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        // Configure required option at FormType level..
        $options = $field->getFormTypeOptions();
        if (array_key_exists("required", $options))
            unset($options["required"]);

        $field->setFormTypeOptions($options);

        // Show formatted value
        if( ($fieldName = $field->getCustomOption("show_field")) ) {

            $field->setLabel(ucfirst($fieldName));
            $field->setFormattedValue("-");

            $childField = $field->getValue();
            if($childField instanceof PersistentCollection) {

                $classMetadata = $childField->getTypeClass();
                if ($classMetadata->hasField($fieldName)) {

                    $entity = $childField->get($this->localeProvider->getLocale());
                    if (!$entity) $entity = $childField->get($this->localeProvider->getDefaultLocale());

                    $formattedValue = ($entity ? $childField->getTypeClass()->getFieldValue($entity, $fieldName) : null);
                    $field->setFormattedValue($formattedValue);
                }
            }
        }
    }
}
