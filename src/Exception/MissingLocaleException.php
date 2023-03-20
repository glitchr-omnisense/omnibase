<?php

namespace Base\Exception;

class MissingLocaleException extends \Exception
{
    /**
     * {@inheritdoc}
     */
    public function getMessageKey()
    {
        return 'Missing locale.';
    }
}
