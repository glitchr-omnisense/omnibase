<?php

namespace Base\Field\Configurator;

use Base\Field\WysiwygField;
use Base\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use const ENT_NOQUOTES;

class WysiwygConfigurator implements FieldConfiguratorInterface
{
    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return WysiwygField::class === $field->getFieldFqcn();
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $length    = $field->getCustomOption(WysiwygField::OPTION_SHORTEN_LENGTH);
        $position  = $field->getCustomOption(WysiwygField::OPTION_SHORTEN_POSITION);
        $separator = $field->getCustomOption(WysiwygField::OPTION_SHORTEN_SEPARATOR);

        $renderAsBoolean = $field->getCustomOption(WysiwygField::OPTION_RENDER_AS_BOOLEAN);
        if ($renderAsBoolean) {
            $field->setValue(!empty($field->getValue()));
        }

        $stripTags = $field->getCustomOption(TextField::OPTION_STRIP_TAGS);
        if ($stripTags) {
            $formattedValue = strip_tags((string) $field->getValue());
        } else {
            $formattedValue = htmlspecialchars((string) $field->getValue(), ENT_NOQUOTES, null, false);
        }

        $field->setFormattedValue(str_shorten($formattedValue, $length, $position, $separator));
    }
}
