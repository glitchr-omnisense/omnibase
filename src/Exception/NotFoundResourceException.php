<?php

namespace Base\Exception;

class NotFoundResourceException extends \Exception
{
    /**
     * {@inheritdoc}
     */
    public function getMessageKey()
    {
        return 'Resource not found.';
    }
}
