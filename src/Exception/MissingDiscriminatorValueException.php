<?php

namespace Base\Exception;

use Exception;

/**
 *
 */
class MissingDiscriminatorValueException extends Exception
{
    /**
     * {}
     */
    public function getMessageKey()
    {
        return 'Discriminator value is missing.';
    }
}
