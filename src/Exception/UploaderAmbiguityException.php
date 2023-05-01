<?php

namespace Base\Exception;

use Exception;

class UploaderAmbiguityException extends Exception
{
    /**
     * {@inheritdoc}
     */
    public function getMessageKey()
    {
        return 'Uploader ambiguity exception. Too many files received';
    }
}
