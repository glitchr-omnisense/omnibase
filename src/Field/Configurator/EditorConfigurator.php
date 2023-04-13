<?php

namespace Base\Field\Configurator;

use Base\Field\EditorField;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;

class EditorConfigurator implements FieldConfiguratorInterface
{
    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return EditorField::class === $field->getFieldFqcn();
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $length    = $field->getCustomOption(EditorField::OPTION_SHORTEN_LENGTH);
        $position  = $field->getCustomOption(EditorField::OPTION_SHORTEN_POSITION);
        $separator = $field->getCustomOption(EditorField::OPTION_SHORTEN_SEPARATOR);

        $renderAsBoolean = $field->getCustomOption(EditorField::OPTION_RENDER_AS_BOOLEAN);
        if ($renderAsBoolean) {
            $field->setValue(!empty($field->getValue()));
        }

        $field->setFormattedValue(str_shorten($field->getFormattedValue(), $length, $position, $separator));
    }
}
