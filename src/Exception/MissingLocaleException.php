<?php

namespace Base\Exception;

use Exception;

class MissingLocaleException extends Exception
{
    /**
     * {@inheritdoc}
     */
    public function getMessageKey()
    {
        return 'Missing locale.';
    }
}
