<?php

namespace Base\Exception;

use Exception;

/**
 *
 */
class InvalidPageException extends Exception
{
    /**
     * {}
     */
    public function getMessageKey()
    {
        return 'Page not found.';
    }
}
