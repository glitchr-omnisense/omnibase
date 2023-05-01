<?php

namespace Base\Exception;

use Exception;

/**
 *
 */
class MissingPublicPathException extends Exception
{
    /**
     * {}
     */
    public function getMessageKey()
    {
        return 'No public path provided.';
    }
}
