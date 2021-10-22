<?php

namespace Base\Service;

use Doctrine\ORM\EntityManagerInterface;

class BaseSettings
{
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }
}