<?php

namespace Base\Field\Configurator;

use Base\Field\ArrayField;
use Base\Traits\BaseTrait;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\CurrencyType;
use Symfony\Component\Form\Extension\Core\Type\LanguageType;
use Symfony\Component\Form\Extension\Core\Type\LocaleType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\PropertyAccess\PropertyAccess;

use function Symfony\Component\String\u;

class ArrayConfigurator implements FieldConfiguratorInterface
{
    use BaseTrait;

    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return ArrayField::class === $field->getFieldFqcn();
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        if (null !== $entryTypeFqcn = $field->getCustomOptions()->get(ArrayField::OPTION_ENTRY_TYPE)) {
            $field->setFormTypeOption('entry_type', $entryTypeFqcn);
        }
        if (null !== $entryOptions = $field->getCustomOptions()->get(ArrayField::OPTION_ENTRY_OPTIONS)) {
            $field->setFormTypeOption('entry_options', $entryOptions);
        }

        $autocompletableFormTypes = [CountryType::class, CurrencyType::class, LanguageType::class, LocaleType::class, TimezoneType::class];
        if (\in_array($entryTypeFqcn, $autocompletableFormTypes, true)) {
            $field->setFormTypeOption('entry_options.attr.data-widget', 'autocomplete');
        }

        $field->setFormTypeOptionIfNotSet('delete_empty', true);

        // TODO: check why this label (hidden by default) is not working properly
        // (generated values are always the same for all elements)
        $field->setFormTypeOptionIfNotSet('entry_options.label', $field->getCustomOptions()->get(ArrayField::OPTION_SHOW_ENTRY_LABEL));

        // collection items range from a simple <input text> to a complex multi-field form
        // the 'entryIsComplex' setting tells if the collection item is so complex that needs a special
        // rendering not applied to simple collection items
        if (null === $field->getCustomOption(ArrayField::OPTION_ENTRY_IS_COMPLEX)) {
            $definesEntryType = null !== $entryTypeFqcn = $field->getCustomOption(ArrayField::OPTION_ENTRY_TYPE);
            $isSymfonyCoreFormType = null !== u($entryTypeFqcn ?? '')->indexOf('Symfony\Component\Form\Extension\Core\Type');
            $isComplexEntry = $definesEntryType && !$isSymfonyCoreFormType;

            $field->setCustomOption(ArrayField::OPTION_ENTRY_IS_COMPLEX, $isComplexEntry);
        }

        if (null !== $patternFieldName = $field->getCustomOptions()->get(ArrayField::OPTION_PATTERN_FIELD_NAME)) {

            $entity = $entityDto->getInstance();
            foreach(explode(".", $patternFieldName) as $propertyPath){

                if(is_object($entity)) $entity = PropertyAccess::createPropertyAccessor()->getValue($entity, $propertyPath);
                else throw new \Exception("Invalid property path for \"".get_class($entity)."\": ".$patternFieldName);
            }

            $formattedValue = $this->resolve($entity, ...PropertyAccess::createPropertyAccessor()->getValue($entityDto->getInstance(), $field->getProperty()));
            $field->setFormattedValue(is_url($this->sanitize($formattedValue)) ? "<a href='".$this->sanitize($formattedValue)."'>".$formattedValue."</a>" : $formattedValue);

        } else {

            $field->setFormattedValue($this->formatCollection($field, $context));
        }
    }

    public function resolve(?string $pattern, ...$patternOpts): ?string
    {
        if(!$pattern) return null;

        $search = [];
        foreach($patternOpts as $index => $_)
            $search[] = "{".$index."}";

        $url = str_replace($search, $patternOpts, $pattern);
        return rtrim(preg_match('/\{[0-9]*\}/', $url) ? null : $url, "/");
    }

    public function sanitize(?string $url): ?string
    {
        if(!$url) return null;

        $parseUrl = parse_url($url);
        $parseUrl["scheme"] = $parseUrl["scheme"] ?? $this->getSettings()->scheme();
        $parseUrl["domain"] = $parseUrl["domain"] ?? $this->getSettings()->domain();
        $parseUrl["path"]   = $this->getService()->getAsset($parseUrl["path"] ?? "");

        return $parseUrl["scheme"] . "://" . $parseUrl["domain"] . $parseUrl["path"];
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
