<?php

namespace Base\Exception;

use Exception;

/**
 *
 */
class OwnerSideException extends Exception
{
    /**
     * {}
     */
    public function getMessageKey()
    {
        return 'Only owner-side modification is allowed using DTO field.';
    }
}
