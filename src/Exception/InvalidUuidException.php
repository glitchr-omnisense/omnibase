<?php

namespace Base\Exception;

use Exception;

class InvalidUuidException extends Exception
{
    /**
     * {@inheritdoc}
     */
    public function getMessageKey()
    {
        return 'Invalid UUID exception.';
    }
}
