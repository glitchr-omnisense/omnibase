<?php

namespace Base\Exception;

use Exception;

/**
 *
 */
class TranslationAmbiguityException extends Exception
{
    /**
     * {}
     */
    public function getMessageKey()
    {
        return 'Intl ambiguity exception.';
    }
}
