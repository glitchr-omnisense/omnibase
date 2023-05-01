<?php

namespace Base\Exception;

use Exception;

/**
 *
 */
class NotFoundResourceException extends Exception
{
    /**
     * {}
     */
    public function getMessageKey()
    {
        return 'Resource not found.';
    }
}
