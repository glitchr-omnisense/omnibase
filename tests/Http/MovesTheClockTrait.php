<?php

namespace Tests\Base\Http;

use Base\Contract\Runtime;
use Base\Service\FormGuard;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\Clock;

/**
 * A visitor who takes the time a person does, without the test waiting for it:
 * the clock is moved ahead instead of sleeping. The forms' guard
 * (Base\Service\FormGuard) refuses a form sent sooner than base.guard.min_delay
 * after it was shown (3 s by default; a site's suite runs with its own, the
 * harness with 0), by the clock - the framework's (Symfony's `clock`, which
 * reads symfony/clock's global clock) or the clock contract of the Foundation
 * (Runtime::clock()). Both are moved, and they outlive the kernels a test
 * boots one after the other; putTheClockBack() (tearDown) puts them back.
 */
trait MovesTheClockTrait
{
    private ?AheadClock $aheadClock = null;
    private ?ClockInterface $clockBefore = null;

    /** The clock $seconds further ahead. */
    protected function moveTheClock(int $seconds): void
    {
        if (null === $this->aheadClock) {
            $this->aheadClock = new AheadClock();
            Runtime::set(ClockInterface::class, $this->aheadClock);
            if (class_exists(Clock::class)) {
                $this->clockBefore = Clock::get();
                Clock::set($this->aheadClock);
            }
        }
        $this->aheadClock->seconds += $seconds;
    }

    /** Between reading a guarded form and sending it: longer than the guard asks of a person, in the kernel booted now. */
    protected function takeTheTimeAPersonTakes(): void
    {
        $this->moveTheClock(static::getContainer()->get(FormGuard::class)->minDelay() + 1);
    }

    protected function putTheClockBack(): void
    {
        if (null === $this->aheadClock) {
            return;
        }
        Runtime::set(ClockInterface::class, null);
        if (null !== $this->clockBefore) {
            Clock::set($this->clockBefore);
        }
        $this->aheadClock = $this->clockBefore = null;
    }
}
