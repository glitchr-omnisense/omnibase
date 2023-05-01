<?php

namespace Base\Exception;

use Exception;

/**
 *
 */
class NotReadableException extends Exception
{
    /**
     * {}
     */
    public function getMessageKey()
    {
        return 'Not readable exception.';
    }
}
