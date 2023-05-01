<?php

namespace Base\Exception;

use Exception;

/**
 *
 */
class InvalidMimeTypeException extends Exception
{
    /**
     * {}
     */
    public function getMessageKey()
    {
        return 'Invalid MIME type exception.';
    }
}
