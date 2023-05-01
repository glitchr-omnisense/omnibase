<?php

namespace Base\Exception;

use Exception;

/**
 *
 */
class MissingLocaleException extends Exception
{
    /**
     * {}
     */
    public function getMessageKey()
    {
        return 'Missing locale.';
    }
}
