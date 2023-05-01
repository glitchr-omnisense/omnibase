<?php

namespace Base\Exception;

use Exception;

class NotReadableException extends Exception
{
    /**
     * {@inheritdoc}
     */
    public function getMessageKey()
    {
        return 'Not readable exception.';
    }
}
