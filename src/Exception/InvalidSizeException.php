<?php

namespace Base\Exception;

class InvalidSizeException extends \Exception
{
    /**
     * {@inheritdoc}
     */
    public function getMessageKey()
    {
        return 'Invalid filesize exception.';
    }
}
