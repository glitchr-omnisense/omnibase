<?php

namespace Base\Exception;

use Exception;

/**
 *
 */
class InvalidUuidException extends Exception
{
    /**
     * {}
     */
    public function getMessageKey()
    {
        return 'Invalid UUID exception.';
    }
}
