<?php

declare(strict_types=1);

namespace Base\EntityDispatcher;

interface EventInterface
{
    public function getObjectManager();

    public function getObjectClass(): string;

    public function getObject(): object;
}
