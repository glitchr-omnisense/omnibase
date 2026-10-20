<?php

namespace Tests\Base\Auth;

/**
 * HttpTestTrait::bootHost() gives PHP's globals the address and agent
 * UserTracker reads; these tests run before the suites that expect none
 * (NotificationMessagesTest's messages of before): they are put back as
 * they were.
 */
trait KeepsServerGlobals
{
    /** @var array<string, mixed> */
    private array $serverBefore = [];

    private function keepServerGlobals(): void
    {
        foreach (['REMOTE_ADDR', 'HTTP_USER_AGENT'] as $key) {
            $this->serverBefore[$key] = \array_key_exists($key, $_SERVER) ? $_SERVER[$key] : self::class;
        }
    }

    private function restoreServerGlobals(): void
    {
        foreach ($this->serverBefore as $key => $value) {
            if (self::class === $value) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $value;
            }
        }
    }
}
