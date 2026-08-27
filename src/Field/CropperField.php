<?php

namespace Base\Field;

use Base\Field\Type\ImageType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Symfony\Contracts\Translation\TranslatableInterface;

class CropperField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_SHOWFIRST = 'showFirst';
    public const OPTION_RENDER_FORMAT = 'renderFormat';

    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/cropper')
            ->setFormType(ImageType::class)
            ->addCssClass('field-cropper')
            ->addCssClass('cropper-widget')
            ->setTemplatePath('@Admin/crud/field/cropper.html.twig')
            ->setTextAlign(TextAlign::CENTER)
            ->setFormTypeOptionIfNotSet('data_class', null)
            ->setCustomOption(self::OPTION_SHOWFIRST, true)
            ->setFormTypeOption('cropper', [])
            ->setCustomOption(self::OPTION_RENDER_FORMAT, 'count');
    }
}
