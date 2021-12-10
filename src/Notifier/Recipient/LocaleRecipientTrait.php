<?php

namespace Base\Notifier\Recipient;

trait LocaleRecipientTrait
{
    private $locale;

    public function getLocale(): string
    {
        return $this->locale;
    }
}
