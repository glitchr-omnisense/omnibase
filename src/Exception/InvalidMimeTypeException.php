<?php

namespace Base\Exception;

class InvalidMimeTypeException extends \Exception
{
    /**
     * {@inheritdoc}
     */
    public function getMessageKey()
    {
        return 'Invalid MIME type exception.';
    }
}