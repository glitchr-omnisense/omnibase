<?php

namespace Base\Form\Common;

use Base\Field\Type\TranslationType;

use Exception;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;

abstract class AbstractType extends \Symfony\Component\Form\AbstractType implements FormTypeInterface
{
    public static function getModelClass(): string
    {
        return str_replace("Type", "Model", static::class);
    }
}
