<?php

namespace Base\Entity;

interface ThreadInterface
{
    public function getAvailableStates(): ?array;
}