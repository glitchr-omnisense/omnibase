<?php

namespace Base\Field;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
final class LinkIdField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_MAX_LENGTH = 'maxLength';

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/id')
            ->setTemplatePath('@EasyAdmin/crud/field/linkId.html.twig')
            ->setFormType(TextType::class)
            ->addCssClass('field-id')
            ->setCustomOption(self::OPTION_MAX_LENGTH, null);
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
}
