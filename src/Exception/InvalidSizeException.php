<?php

namespace Base\Exception;

use Exception;

/**
 *
 */
class InvalidSizeException extends Exception
{
    /**
     * {}
     */
    public function getMessageKey()
    {
        return 'Invalid filesize exception.';
    }
}
