<?php

namespace Base\Exception;

use Exception;

/**
 *
 */
class NotDeletableException extends Exception
{
    /**
     * {}
     */
    public function getMessageKey()
    {
        return 'Not deletable exception.';
    }
}
