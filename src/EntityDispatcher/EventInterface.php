<?php

declare(strict_types=1);

namespace Base\EntityDispatcher;

/** An entity's event. Since 6.0 the unit of work it was written through is getUnitOfWork() (5.x's getObjectManager() is gone). */
interface EventInterface
{
    public function getUnitOfWork(): \Omnibridge\Persistence\UnitOfWork;

    public function getObjectClass(): string;

    public function getObject(): object;
}
