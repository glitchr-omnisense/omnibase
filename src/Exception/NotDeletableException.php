<?php

namespace Base\Exception;

use Exception;

class NotDeletableException extends Exception
{
    /**
     * {@inheritdoc}
     */
    public function getMessageKey()
    {
        return 'Not deletable exception.';
    }
}
