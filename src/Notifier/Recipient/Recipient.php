<?php

namespace Base\Notifier\Recipient;

use Base\Service\Localizer;

class Recipient extends \Symfony\Component\Notifier\Recipient\Recipient implements LocaleRecipientInterface, TimezoneRecipientInterface
{
    use LocaleRecipientTrait;
    use TimezoneRecipientTrait;

    public function __toString() {

        $technicalRecipientStr  = "\"". $this->getEmail() . "\"";
        $technicalRecipientStr .= $this->getPhone()  ? " / (" . $this->getPhone() .")" : "";
        $technicalRecipientStr .= $this->getLocale() ? " / " . $this->getLocale() : "";

        return $technicalRecipientStr;
    }

    public function __construct(?string $email = null, ?string $phone = null, ?string $locale = null, ?string $timezone = null)
    {
        parent::__construct($email ?? '', $phone ?? '');

        if(!$locale)
            $locale = Localizer::getDefaultLocale();
        if(!$timezone)
            $timezone = "UTC";

        $this->locale = $locale;
        $this->timezone = $timezone;
    }

    /**
     * @return $this
     */
    public function locale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * @return $this
     */
    public function timezone(string $timezone): self
    {
        $this->timezone = $timezone;

        return $this;
    }
}
