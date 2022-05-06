<?php

namespace Base\Field\Configurator;

use Base\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\CurrencyType;
use Symfony\Component\Form\Extension\Core\Type\LanguageType;
use Symfony\Component\Form\Extension\Core\Type\LocaleType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use function Symfony\Component\String\u;

class CollectionConfigurator implements FieldConfiguratorInterface
{
    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return CollectionField::class === $field->getFieldFqcn();
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        if (null !== $entryTypeFqcn = $field->getCustomOptions()->get(CollectionField::OPTION_ENTRY_TYPE)) {
            $field->setFormTypeOption('entry_type', $entryTypeFqcn);
        }
        if (null !== $entryOptions = $field->getCustomOptions()->get(CollectionField::OPTION_ENTRY_OPTIONS)) {
            $field->setFormTypeOption('entry_options', $entryOptions);
        }

        $autocompletableFormTypes = [CountryType::class, CurrencyType::class, LanguageType::class, LocaleType::class, TimezoneType::class];
        if (\in_array($entryTypeFqcn, $autocompletableFormTypes, true)) {
            $field->setFormTypeOption('entry_options.attr.data-widget', 'autocomplete');
        }

        $field->setFormTypeOptionIfNotSet('by_reference', false);
        $field->setFormTypeOptionIfNotSet('delete_empty', true);

        // collection items range from a simple <input text> to a complex multi-field form
        // the 'entryIsComplex' setting tells if the collection item is so complex that needs a special
        // rendering not applied to simple collection items
        if (null === $field->getCustomOption(CollectionField::OPTION_ENTRY_IS_COMPLEX)) {
            $definesEntryType = null !== $entryTypeFqcn = $field->getCustomOption(CollectionField::OPTION_ENTRY_TYPE);
            $isSymfonyCoreFormType = null !== u($entryTypeFqcn ?? '')->indexOf('Symfony\Component\Form\Extension\Core\Type');
            $isComplexEntry = $definesEntryType && !$isSymfonyCoreFormType;

            $field->setCustomOption(CollectionField::OPTION_ENTRY_IS_COMPLEX, $isComplexEntry);
        }

        $field->setFormattedValue($this->formatCollection($field, $context));
    }

    private function formatCollection(FieldDto $field, AdminContext $context)
    {
        return $this->countNumElements($field->getValue());
    }

    private function countNumElements($collection): int
    {
        if (null === $collection) {
            return 0;
        }

        if (is_countable($collection)) {
            return \count($collection);
        }

        if ($collection instanceof \Traversable) {
            return iterator_count($collection);
        }

        return 0;
    }
}
