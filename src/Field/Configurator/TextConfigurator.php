<?php

namespace Base\Field\Configurator;

use Base\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;

use function Symfony\Component\String\u;

class TextConfigurator implements FieldConfiguratorInterface
{
    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return $field->getFieldFqcn() === TextField::class;
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        if (null === $value = $field->getValue()) {
            return;
        }

        if (is_object($value) && !method_exists($value, '__toString')) {
            throw new \RuntimeException(sprintf('The value of the "%s" field of the entity with ID = "%s" can\'t be converted into a string, so it cannot be represented by a TextField or a TextareaField.', $field->getProperty(), $entityDto->getPrimaryKeyValue()));
        }

        $renderAsBoolean = $field->getCustomOption(TextField::OPTION_RENDER_AS_BOOLEAN);
        if ($renderAsBoolean) {
            $field->setValue(!empty($field->getValue()));
        }

        $renderAsHtml = $field->getCustomOption(TextField::OPTION_RENDER_AS_HTML);
        $stripTags = $field->getCustomOption(TextField::OPTION_STRIP_TAGS);
        if ($stripTags) {
            $formattedValue = strip_tags((string)$field->getValue());
        } else {
            $formattedValue = htmlspecialchars((string)$field->getValue(), \ENT_NOQUOTES, null, false);
        }

        $configuredMaxLength = $field->getCustomOption(TextField::OPTION_MAX_LENGTH);
        // when contents are rendered as HTML, "max length" option is ignored to prevent
        // truncating contents in the middle of an HTML tag, which messes the entire backend
        if (!$renderAsHtml && !$renderAsBoolean) {
            $isDetailAction = Action::DETAIL === $context->getCrud()->getCurrentAction();
            $defaultMaxLength = $isDetailAction ? \PHP_INT_MAX : 64;
            $formattedValue = u($formattedValue)->truncate($configuredMaxLength ?? $defaultMaxLength, '…')->toString();
        }

        $field->setFormattedValue($formattedValue);
    }
}
