<?php

namespace Base\Field;

use Base\Field\Type\AvatarType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;

use Base\Field\Type\FileType;
use Base\Field\Type\ImageType;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;

final class AvatarField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_RENDER_FORMAT  = "renderFormat";

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/file')
            ->setFormType(AvatarType::class)
            ->addCssClass('field-file')
            ->addCssClass('file-widget')
            ->setTemplatePath('@EasyAdmin/crud/field/file.html.twig')
            ->setTextAlign(TextAlign::CENTER)
            ->setFormTypeOptionIfNotSet("data_class", null)
            ->setCustomOption(self::OPTION_RENDER_FORMAT, "avatar");
    }

    public function allowDelete(bool $allowDelete = true): self
    {
        $this->setFormTypeOption("allow_delete", $allowDelete);
        return $this;
    }

}
