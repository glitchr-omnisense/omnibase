<?php

namespace Base\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;

use Symfony\Component\Form\Extension\Core\Type\IntegerType;

/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
final class YearField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/integer')
            ->setTemplatePath('@EasyAdmin/crud/field/year.html.twig')
            ->setFormType(IntegerType::class)
            ->addCssClass('field-integer');
    }
}
