<?php

namespace Base\Exception;

class MissingDiscriminatorMapException extends \Exception
{
    /**
     * {@inheritdoc}
     */
    public function getMessageKey()
    {
        return 'Discriminator map is missing.';
    }
}
