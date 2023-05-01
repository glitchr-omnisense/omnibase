<?php

namespace Base\Notifier\Recipient;

/**
 *
 */
trait LocaleRecipientTrait
{
    private string $locale;

    public function getLocale(): string
    {
        return $this->locale;
    }
}
