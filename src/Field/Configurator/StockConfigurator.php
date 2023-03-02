<?php

namespace Base\Field\Configurator;

use Base\Field\SlugField;
use Base\Field\StockField;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Intl\IntlFormatter;

final class StockConfigurator implements FieldConfiguratorInterface
{
    private $intlFormatter;

    public function __construct(IntlFormatter $intlFormatter)
    {
        $this->intlFormatter = $intlFormatter;
    }

    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return StockField::class === $field->getFieldFqcn();
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        if (null === $value = $field->getValue()) {
            $field->setFormattedValue("");
            return;
        }

        $targetFieldName = $field->getCustomOption(StockField::OPTION_TARGET_FIELD_NAME);
        $field->setFormTypeOption('target', $targetFieldName);

        $scale = $field->getCustomOption(StockField::OPTION_NUM_DECIMALS);
        $roundingMode = $field->getCustomOption(StockField::OPTION_ROUNDING_MODE);
        $isStoredAsString = $field->getCustomOption(StockField::OPTION_STORED_AS_STRING);

        $field->setFormTypeOptionIfNotSet('input', $isStoredAsString ? 'string' : 'number');
        $field->setFormTypeOptionIfNotSet('rounding_mode', $roundingMode);
        $field->setFormTypeOptionIfNotSet('scale', $scale);

        $formatterAttributes = ['rounding_mode' => $this->getRoundingModeAsString($roundingMode)];
        if (null !== $scale) {
            $formatterAttributes['fraction_digit'] = $scale;
        }

        $field->setFormattedValue($this->intlFormatter->formatNumber($value, $formatterAttributes));
    }

    private function getRoundingModeAsString(int $mode): string
    {
        return [
            \NumberFormatter::ROUND_DOWN => 'down',
            \NumberFormatter::ROUND_FLOOR => 'floor',
            \NumberFormatter::ROUND_UP => 'up',
            \NumberFormatter::ROUND_CEILING => 'ceiling',
            \NumberFormatter::ROUND_HALFDOWN => 'halfdown',
            \NumberFormatter::ROUND_HALFEVEN => 'halfeven',
            \NumberFormatter::ROUND_HALFUP => 'halfup',
        ][$mode];
    }
}
