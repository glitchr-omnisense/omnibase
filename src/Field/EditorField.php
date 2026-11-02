<?php

namespace Base\Field;


use Symfony\Contracts\Translation\TranslatableInterface;

final class EditorField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/hidden')
            ->setKind(Kind::EDITOR);
    }
}
