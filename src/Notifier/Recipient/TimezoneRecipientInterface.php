<?php

namespace Base\Notifier\Recipient;

use Symfony\Component\Notifier\Recipient\RecipientInterface;

interface TimezoneRecipientInterface extends RecipientInterface
{
    public function getTimezone(): string;
}