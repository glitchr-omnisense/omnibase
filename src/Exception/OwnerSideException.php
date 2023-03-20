<?php

namespace Base\Exception;

class OwnerSideException extends \Exception
{
    /**
     * {@inheritdoc}
     */
    public function getMessageKey()
    {
        return 'Only owner-side modification is allowed using DTO field.';
    }
}
