<?php

namespace Base\Field;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;

final class IdField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_MAX_LENGTH = 'maxLength';
    public const OPTION_ADD_LINK = 'addLink';
    public const OPTION_IMPERSONATE = 'impersonate';

    public static function new(string $propertyName = "id", ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/id')
            ->setTemplatePath('@EasyAdmin/crud/field/id.html.twig')
            ->setFormType(HiddenType::class)
            ->addCssClass('field-id')
            ->setCustomOption(self::OPTION_MAX_LENGTH, null)
            ->setCustomOption(self::OPTION_IMPERSONATE, true)
            ->setCustomOption(self::OPTION_ADD_LINK, true);
    }

    /**
     * Set maxLength to -1 to define an unlimited max length.
     */
    public function setMaxLength(int $length): self
    {
        if (0 === $length)
            throw new \InvalidArgumentException(sprintf('The argument of the "%s()" method must be a positive integer or -1 (for unlimited length) (%d given).', __METHOD__, $length));

        $this->setCustomOption(self::OPTION_MAX_LENGTH, $length);

        return $this;
    }

    public function enableImpersonation() 
    {
        $this->setCustomOption(self::OPTION_IMPERSONATE, true);
        return $this;
    }
    public function disableImpersonation() 
    {
        $this->setCustomOption(self::OPTION_IMPERSONATE, false);
        return $this;
    }

    public function enableLink() 
    {
        $this->setCustomOption(self::OPTION_ADD_LINK, true);
        return $this;
    }
    public function disableLink() 
    {
        $this->setCustomOption(self::OPTION_ADD_LINK, false);
        return $this;
    }
}
