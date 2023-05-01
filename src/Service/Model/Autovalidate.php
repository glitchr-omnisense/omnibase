<?php

namespace Base\Service\Model;

use Base\Service\TranslatorInterface;
use Base\Validator\Constraint;

class Autovalidate
{
    private ?TranslatorInterface $translator;

    public function __construct(TranslatorInterface $translator = null)
    {
        $this->translator = $translator;
    }

    public function validate($value, Constraint ...$constraints): bool
    {
        //TODO
        return true;
    }
}
