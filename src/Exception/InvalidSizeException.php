<?php

namespace Base\Exception;

use Exception;

class InvalidSizeException extends Exception
{
    /**
     * {@inheritdoc}
     */
    public function getMessageKey()
    {
        return 'Invalid filesize exception.';
    }
}
