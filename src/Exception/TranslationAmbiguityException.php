<?php

namespace Base\Exception;

class TranslationAmbiguityException extends \Exception
{
    /**
     * {@inheritdoc}
     */
    public function getMessageKey()
    {
        return 'Translation ambiguity exception.';
    }
}