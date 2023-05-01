<?php

namespace Base\Exception;

use Exception;

class NotWritableException extends Exception
{
    /**
     * {@inheritdoc}
     */
    public function getMessageKey()
    {
        return 'Not writable exception.';
    }
}
