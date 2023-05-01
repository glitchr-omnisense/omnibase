<?php

namespace Base\Exception;

use Exception;

class OwnerSideException extends Exception
{
    /**
     * {@inheritdoc}
     */
    public function getMessageKey()
    {
        return 'Only owner-side modification is allowed using DTO field.';
    }
}
