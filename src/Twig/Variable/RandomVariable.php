<?php

namespace Base\Twig\Variable;
use Symfony\Component\Uid\Uuid;

class RandomVariable
{
    public function uuidv4() { return Uuid::v4(); }
    public function rand() { return rand(); }
}
