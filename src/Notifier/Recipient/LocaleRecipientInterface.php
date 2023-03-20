<?php

namespace Base\Notifier\Recipient;

use Symfony\Component\Notifier\Recipient\RecipientInterface;

interface LocaleRecipientInterface extends RecipientInterface
{
    public function getLocale(): string;
}
