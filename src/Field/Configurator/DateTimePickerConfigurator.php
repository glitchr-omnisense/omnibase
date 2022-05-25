<?php

namespace Base\Field\Configurator;

use Doctrine\DBAL\Types\Types;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimeField;
use EasyCorp\Bundle\EasyAdminBundle\Intl\IntlFormatter;

use Base\Field\DateTimePickerField;

class DateTimePickerConfigurator implements FieldConfiguratorInterface
{
    private $intlFormatter;

    public function __construct()
    {
        $this->intlFormatter = new IntlFormatter();
    }

    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return \in_array($field->getFieldFqcn(), [DateTimePickerField::class, DateField::class, TimeField::class], true);
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $crud = $context->getCrud();

        $defaultTimezone = $crud->getTimezone();

        $timezone = $field->getCustomOption(DateTimePickerField::OPTION_TIMEZONE) ?? $defaultTimezone;

        $dateFormat = null;
        $timeFormat = null;
        $icuDateTimePattern = '';

        $formattedValue = $field->getValue() ?? $field->getCustomOption(DateTimePickerField::OPTION_DEFAULT);

        if (DateTimePickerField::class === $field->getFieldFqcn()) {
            [$defaultDatePattern, $defaultTimePattern] = $crud->getDateTimePattern();
            $datePattern = $field->getCustomOption(DateTimePickerField::OPTION_DATE_PATTERN) ?? $defaultDatePattern;
            $timePattern = $field->getCustomOption(DateTimePickerField::OPTION_TIME_PATTERN) ?? $defaultTimePattern;
            if (\in_array($datePattern, DateTimePickerField::VALID_DATE_FORMATS, true)) {
                $dateFormat = $datePattern;
                $timeFormat = $timePattern;
            } else {
                $icuDateTimePattern = $datePattern;
            }

            $formattedValue = $this->intlFormatter->formatDateTime($field->getValue(), $dateFormat, $timeFormat, $icuDateTimePattern, $timezone);
        } elseif (DateField::class === $field->getFieldFqcn()) {
            $dateFormatOrPattern = $field->getCustomOption(DateField::OPTION_DATE_PATTERN) ?? $crud->getDatePattern();
            if (\in_array($dateFormatOrPattern, DateTimePickerField::VALID_DATE_FORMATS, true)) {
                $dateFormat = $dateFormatOrPattern;
            } else {
                $icuDateTimePattern = $dateFormatOrPattern;
            }

            $formattedValue = $this->intlFormatter->formatDate($field->getValue(), $dateFormat, $icuDateTimePattern, $timezone);
        } elseif (TimeField::class === $field->getFieldFqcn()) {
            $timeFormatOrPattern = $field->getCustomOption(TimeField::OPTION_TIME_PATTERN) ?? $crud->getTimePattern();
            if (\in_array($timeFormatOrPattern, DateTimePickerField::VALID_DATE_FORMATS, true)) {
                $timeFormat = $timeFormatOrPattern;
            } else {
                $icuDateTimePattern = $timeFormatOrPattern;
            }

            $formattedValue = $this->intlFormatter->formatTime($field->getValue(), $timeFormat, $icuDateTimePattern, $timezone);
            
        }

        $widgetOption = $field->getCustomOption(DateTimePickerField::OPTION_WIDGET);
        if (DateTimePickerField::WIDGET_NATIVE === $widgetOption) {
            $field->setFormTypeOption('widget', 'single_text');
            $field->setFormTypeOption('html5', true);
        } elseif (DateTimePickerField::WIDGET_CHOICE === $widgetOption) {
            $field->setFormTypeOption('widget', 'choice');
            $field->setFormTypeOption('html5', true);
        } elseif (DateTimePickerField::WIDGET_TEXT === $widgetOption) {
            $field->setFormTypeOption('widget', 'single_text');
            $field->setFormTypeOption('html5', false);
        }

        $field->setFormattedValue($formattedValue);

        // check if the property is immutable, but only if it's a real Doctrine entity property
        if (!$entityDto->hasProperty($field->getProperty())) {
            return;
        }
        $doctrineDataType = $entityDto->getPropertyMetadata($field->getProperty())->get('type');
        $isImmutableDateTime = \in_array($doctrineDataType, [Types::DATETIMETZ_IMMUTABLE, Types::DATETIME_IMMUTABLE, Types::DATE_IMMUTABLE, Types::TIME_IMMUTABLE], true);
        if ($isImmutableDateTime) {
            $field->setFormTypeOptionIfNotSet('input', 'datetime_immutable');
        }
    }
}