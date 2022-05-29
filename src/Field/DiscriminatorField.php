<?php

namespace Base\Field;

use Base\Field\Type\DiscriminatorType;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

class DiscriminatorField extends SelectField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_DISCRIMINATOR_AUTOLOAD = 'discriminatorAutoload';
    public const OPTION_SHOW_COLUMN            = 'discriminatorColumn';
    public const OPTION_SHOW_INLINE            = 'discriminatorInline';
    public const OPTION_SHOW_LAST_ONLY         = 'discriminatorLastEntry';

    public static function new(string $propertyName = "id", ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label ?? "Type")
            ->setTemplateName('crud/field/text')
            ->setFormType(HiddenType::class)
            ->setCustomOption(self::OPTION_SHOW, self::SHOW_ALL)
            ->setCustomOption(self::OPTION_SHOW_FIRST, self::SHOW_ALL)
            ->setCustomOption(self::OPTION_DISCRIMINATOR_AUTOLOAD, false)
            ->setCustomOption(self::OPTION_SHOW_COLUMN, false)
            ->setCustomOption(self::OPTION_SHOW_INLINE, true)
            ->setCustomOption(self::OPTION_SHOW_LAST_ONLY, false)
            ->setCustomOption(self::OPTION_RENDER_FORMAT, "text")
            ->setIconAlign(TextAlign::RIGHT)->hideOnForm()
            ->setTextAlign(TextAlign::LEFT)->setColumns(6)
            ->setTemplatePath('@EasyAdmin/crud/field/select.html.twig');
    }

    public function showColumnLabel(bool $show = true): self
    {
        $this->setCustomOption(self::OPTION_SHOW_COLUMN, $show);
        return $this;
    }
    public function showInline(bool $inline = true): self
    {
        $this->setCustomOption(self::OPTION_SHOW_INLINE, $inline);
        return $this;
    }
    public function showLastEntry(bool $show = true): self
    {
        $this->setCustomOption(self::OPTION_SHOW_LAST_ONLY, $show);
        return $this;
    }
}
