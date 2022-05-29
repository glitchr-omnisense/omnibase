<?php

namespace Base\Exception;

class NotDeletableException extends \Exception
{
    /**
     * {@inheritdoc}
     */
    public function getMessageKey()
    {
        return 'Not deletable exception.';
    }
}