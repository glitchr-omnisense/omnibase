<?php

namespace Base\Field;

use Base\Field\Type\AssociationFileType;
use Base\Field\Type\AvatarType;
use Base\Field\Type\ImageType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;

use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;

final class AssociationFileField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_SHOWFIRST = "showFirst";
    public const OPTION_ICONS   = 'icons';

    public const OPTION_RENDER_FORMAT   = "renderFormat";
    public const OPTION_CRUD_CONTROLLER = 'crudControllerFqcn';

    public const OPTION_RELATED_URL = 'relatedUrl';
    public const OPTION_DOCTRINE_ASSOCIATION_TYPE = 'associationType';
    public const OPTION_CLASS          = 'class';

    public const OPTION_DISPLAY_LIMIT = 'displayLimit';
    public const OPTION_ICON_ALIGN    = 'iconAlign';

    public const OPTION_SHOW_FIRST      = 'showFirst';
    public const OPTION_SHOW            = 'show';

    public const NO_SHOW        = 0;
    public const SHOW_NAME_ONLY = 1;
    public const SHOW_ICON_ONLY = 2;
    public const SHOW_ALL       = 3;

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/entity')
            ->setFormType(AssociationFileType::class)
            ->addCssClass('field-entity')
            ->addCssClass('file-widget')
            ->setTemplatePath('@EasyAdmin/crud/field/association.html.twig')
            ->setTextAlign(TextAlign::CENTER)
            ->setFormTypeOptionIfNotSet("class", null)
            ->setCustomOption(self::OPTION_RENDER_FORMAT, "count");
    }

    public function setClass(?string $class = null)
    {
        $this->setFormTypeOption(self::OPTION_CLASS, $class);
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
            ->setFormTypeOption("form_type", ImageType::class);

        return $this;
    }

    public function renderAsAvatar(): self
    {
        $this->setCustomOption(self::OPTION_RENDER_FORMAT, "avatar")
             ->setFormTypeOption("form_type", AvatarType::class);

        return $this;
    }

    public function setFieldFile(string $fieldFile): self
    {
        $this->setFormTypeOption("entity_file", $fieldFile);
        return $this;
    }

    public function setFieldData(array|callable $data): self
    {
        $this->setFormTypeOption("entity_data", $data);
        return $this;
    }

    public function setTextAlign(string $textAlign)
    {
        $this->setIconAlign($textAlign);
        $this->dto->setTextAlign($textAlign);
        return $this;
    }

    public function setIconAlign(string $iconAlign)
    {
        $this->setCustomOption(self::OPTION_ICON_ALIGN, $iconAlign);
        return $this;
    }

    public function allowMultipleChoices(bool $allow = true)
    {
        $this->setFormTypeOptionIfNotSet("multiple", $allow);
        return $this;
    }

    public function allowDelete(bool $allow = true)
    {
        $this->setFormTypeOption("allow_delete", $allow);
        return $this;
    }

    public function setMaxSize(int $filesize) {

        $this->setFormTypeOption("max_size", $filesize);
        return $this;
    }

    public function setMaxFiles(int $nFiles) {

        $this->setFormTypeOption("max_files", $nFiles);
        return $this;
    }

    public function setMimeTypes(array $mimeTypes) {

        $this->setFormTypeOption("mime_types", $mimeTypes);
        return $this;
    }

    public function setDisplayLimit(int $limit = 2)
    {
        $this->setCustomOption(self::OPTION_DISPLAY_LIMIT, $limit);
        return $this;
    }

    public function setIcons(array $icons)
    {
        $this->setCustomOption(self::OPTION_ICONS, $icons);
        return $this;
    }

    public function showFirst(int $show = self::SHOW_ALL)
    {
        $this->setCustomOption(self::OPTION_SHOW_FIRST, $show);
        return $this;
    }

    public function show(int $show = self::SHOW_ALL)
    {
        $this->setCustomOption(self::OPTION_SHOW, $show);
        return $this;
    }

}
