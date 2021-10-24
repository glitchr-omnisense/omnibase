<?php

namespace Base\Field;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;

final class BooleanField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_RENDER_AS_SWITCH = 'renderAsSwitch';
    public const OPTION_CONFIRMATION_MODAL_ON_CHECK = 'confirmationModalOnCheck';
    public const OPTION_CONFIRMATION_MODAL_ON_UNCHECK = 'confirmationModalOnUncheck';

    /** @internal */
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
            ->setFormType(CheckboxType::class)
            ->addCssClass('field-boolean')
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
