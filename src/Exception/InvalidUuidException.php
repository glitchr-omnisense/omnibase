<?php

namespace Base\Exception;

class InvalidUuidException extends \Exception
{
    /**
     * {@inheritdoc}
     */
    public function getMessageKey()
    {
        return 'Invalid UUID exception.';
    }
}
