<?php

namespace Base\Twig\Variable;

use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV4;

/**
 *
 */
class RandomVariable
{
    /**
     * @return UuidV4
     */
    public function uuidv4()
    {
        return Uuid::v4();
    }

    /**
     * @return int
     */
    public function rand()
    {
        return rand();
    }
}
