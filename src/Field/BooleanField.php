<?php

namespace Base\Field;

use Base\Field\Type\BooleanType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;

final class BooleanField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_RENDER_AS_SWITCH = 'switch';
    public const OPTION_CONFIRMATION_MODAL_ON_CHECK = 'confirmation[onCheck]';
    public const OPTION_CONFIRMATION_MODAL_ON_UNCHECK = 'confirmation[onUncheck]';
    
    /** @internal */
    public const CSRF_TOKEN_NAME = 'ea-toggle';
    public const OPTION_TOGGLE_URL = 'toggleUrl';

    /**
     * @param string|false|null $label
     */
    public static function new(string $propertyName, $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/boolean')
            ->setTemplatePath('@EasyAdmin/crud/field/boolean.html.twig')
            ->setFormType(BooleanType::class)
            ->addCssClass('field-boolean')
            ->setTextAlign(TextAlign::CENTER)
            ->setCustomOption(self::OPTION_RENDER_AS_SWITCH, true)
            ->setCustomOption(self::OPTION_CONFIRMATION_MODAL_ON_CHECK, false)
            ->setCustomOption(self::OPTION_CONFIRMATION_MODAL_ON_UNCHECK, false);
    }

    public function renderAsSwitch(bool $isASwitch = true): self
    {
        $this->setCustomOption(self::OPTION_RENDER_AS_SWITCH, $isASwitch);

        return $this;
    }

    public function withConfirmation(bool $onCheck = true, bool $onUncheck = true): self
    {
        $this->setCustomOption(self::OPTION_CONFIRMATION_MODAL_ON_CHECK, $onCheck);
        $this->setCustomOption(self::OPTION_CONFIRMATION_MODAL_ON_UNCHECK, $onUncheck);

        return $this;
    }
}
