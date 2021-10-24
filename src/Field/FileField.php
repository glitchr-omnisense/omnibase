<?php

namespace Base\Field;

use Base\Field\Type\AvatarType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;

use Base\Field\Type\FileType;
use Base\Field\Type\ImageType;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;

final class FileField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_RENDER_FORMAT  = "renderFormat";

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/file')
            ->setFormType(FileType::class)
            ->addCssClass('field-file')
            ->addCssClass('file-widget')
            ->setTemplatePath('@EasyAdmin/crud/field/file.html.twig')
            ->setTextAlign(TextAlign::CENTER)
            ->setFormTypeOptionIfNotSet("data_class", null);
    }

    public function allowDelete(bool $allowDelete = true): self
    {
        $this->setFormTypeOption("allow_delete", $allowDelete);
        return $this;
    }

    public function setMultipleFiles(bool $multipleFiles = true): self
    {
        $this->setFormTypeOption("multiple", $multipleFiles);
        return $this;
    }

    public function renderAsText(): self
    {
        $this->setCustomOption(self::OPTION_RENDER_FORMAT, "text");
        return $this;
    }

    public function renderAsCount(): self
    {
        $this->setCustomOption(self::OPTION_RENDER_FORMAT, "count");
        return $this;
    }

    public function renderAsImage(): self
    {
        $this->setCustomOption(self::OPTION_RENDER_FORMAT, "image")
             ->setFormType(ImageType::class);
        return $this;
    }
}
