<?php

namespace Base\Field;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Symfony\Component\Form\Extension\Core\Type\EmailType;

class EmailField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_ICON_CUSTOM = 'icon';
    public const OPTION_SHOW = 'show';
    public const SHOW_ICON   = "icon";
    public const SHOW_EMAIL  = "email";
    public const SHOW_ALL    = "all";

    public function setTargetFieldName(string $show): self
    {
        $this->setCustomOption(self::OPTION_SHOW, $show);
        return $this;
    }

    public static function new(string $propertyName, $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/email')
            ->setTemplatePath('@EasyAdmin/crud/field/email.html.twig')
            ->setFormType(EmailType::class)
            ->setTextAlign(TextAlign::CENTER)
            ->setCustomOption(self::OPTION_SHOW, self::SHOW_ICON)
            ->setCustomOption(self::OPTION_ICON_CUSTOM, "fas fa-at")
            ->setDefaultColumns('col-md-6 col-xxl-5');
    }
}
