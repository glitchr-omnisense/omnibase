<?php

namespace Base\Notifier\Recipient;

use Base\Service\LocaleProvider;

class Recipient extends \Symfony\Component\Notifier\Recipient\Recipient implements LocaleRecipientInterface
{
    use LocaleRecipientTrait;

    public function __toString() {
        if($this->getEmail()) return $this->getEmail();
        if($this->getPhone()) return $this->getPhone();
    }

    public function __construct(?string $email = null, ?string $phone = null, ?string $locale = null)
    {
        parent::__construct($email ?? '', $phone ?? '');

        if(!$locale)
            $locale = LocaleProvider::getDefaultLocale();

        $this->locale = $locale;
    }

    /**
     * @return $this
     */
    public function locale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }
}
