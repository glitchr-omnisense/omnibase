<?php

namespace Base\Notifier\Recipient;

use Base\Service\LocaleProvider;

class Recipient extends \Symfony\Component\Notifier\Recipient\Recipient implements LocaleRecipientInterface
{
    use LocaleRecipientTrait;

    public function __toString() {

        $technicalRecipientStr  = "\"". $this->getEmail() . "\"";
        $technicalRecipientStr .= $this->getPhone()  ? " / (" . $this->getPhone() .")" : "";
        $technicalRecipientStr .= $this->getLocale() ? " / " . $this->getLocale() : "";

        return $technicalRecipientStr;
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
