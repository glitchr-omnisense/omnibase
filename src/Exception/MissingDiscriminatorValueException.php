<?php

namespace Base\Exception;

class MissingDiscriminatorValueException extends \Exception
{
    /**
     * {@inheritdoc}
     */
    public function getMessageKey()
    {
        return 'Discriminator value is missing.';
    }
}