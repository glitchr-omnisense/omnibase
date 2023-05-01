<?php

namespace Base\Exception;

use Exception;

/**
 *
 */
class NotWritableException extends Exception
{
    /**
     * {}
     */
    public function getMessageKey()
    {
        return 'Not writable exception.';
    }
}
