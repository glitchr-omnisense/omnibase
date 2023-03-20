<?php

namespace Base\Field;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;

use Base\Field\Type\ImageType;

class ImageField extends FileField implements FieldInterface
{
    public const OPTION_RENDER_FORMAT  = "renderFormat";

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/file')
            ->setFormType(ImageType::class)
            ->addCssClass('field-file')
            ->addCssClass('file-widget')
            ->setTemplatePath('@EasyAdmin/crud/field/file.html.twig')
            ->setTextAlign(TextAlign::CENTER)
            ->setFormTypeOptionIfNotSet("data_class", null)
            ->setCustomOption(self::OPTION_SHOWFIRST, true)
            ->setCustomOption(self::OPTION_RENDER_FORMAT, "image");
    }

    public function setCropper(null|array|bool $cropper = true)
    {
        if (is_bool($cropper)) {
            $cropper = $cropper ? [] : null;
        }
        $this->setFormTypeOption("cropper", $cropper);
        return $this;
    }
}
