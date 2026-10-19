<?php

namespace Tests\Base\Http;

use Psr\Clock\ClockInterface;

/**
 * The machine's clock, a number of seconds ahead: what MovesTheClockTrait puts
 * in place of the clock while a test plays a visitor who takes their time.
 * It keeps running (not frozen), so whatever else reads it sees time pass as usual.
 */
final class AheadClock implements ClockInterface
{
    public function __construct(public int $seconds = 0)
    {
    }

    public function now(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable())->modify(sprintf('%+d seconds', $this->seconds));
    }
}
