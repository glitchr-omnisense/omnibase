<?php

namespace Base\Field;

use Base\Field\Type\VideoType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;

/**
 *
 */
class VideoField extends FileField implements FieldInterface
{
    public const OPTION_RENDER_FORMAT = 'renderFormat';

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/file')
            ->setFormType(VideoType::class)
            ->addCssClass('field-file')
            ->addCssClass('file-widget')
            ->setTemplatePath('@EasyAdmin/crud/field/file.html.twig')
            ->setTextAlign(TextAlign::CENTER)
            ->setFormTypeOptionIfNotSet('data_class', null)
            ->setCustomOption(self::OPTION_SHOWFIRST, true)
            ->setCustomOption(self::OPTION_RENDER_FORMAT, 'count');
    }
}
