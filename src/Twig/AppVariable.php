<?php

namespace Base\Twig;

use Base\Component\HttpFoundation\Referrer;
use Base\Service\BaseSettings;
use Base\Traits\ProxyTrait;

class AppVariable
{
    use ProxyTrait;

    public function __construct(\Symfony\Bridge\Twig\AppVariable $appVariable, BaseSettings $baseSettings, Referrer $referrer)
    {
        $this->baseSettings = $baseSettings;
        $this->referrer = $referrer;

        $this->setProxy($appVariable);
    }

    public function settings() { return $this->baseSettings->get("app.settings") ?? []; }
    public function getReferrer() { return $this->referrer; }
}
