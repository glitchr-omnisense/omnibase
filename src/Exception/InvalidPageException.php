<?php

namespace Base\Exception;

class InvalidPageException extends \Exception
{
    /**
     * {@inheritdoc}
     */
    public function getMessageKey()
    {
        return 'Page not found.';
    }
}
