<?php

namespace Base\Contract;

use Psr\Clock\ClockInterface;

/**
 * The clock of the machine, the registry's clock when no framework gave one
 * (Base\Contract\Runtime::clock()). Under Symfony the registry holds the
 * framework's `clock` service instead (symfony/clock, a PSR-20 clock that a
 * test can freeze).
 */
final class SystemClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
