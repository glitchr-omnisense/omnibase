<?php

namespace Base\Notifier\Recipient;

trait TimezoneRecipientTrait
{
    private string $timezone;

    public function getTimezone(): string
    {
        return $this->timezone;
    }
}
