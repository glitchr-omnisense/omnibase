<?php

namespace Base\Twig;

use Base\Component\HttpFoundation\Referrer;
use Base\Service\BaseSettings;
use Base\Traits\ProxyTrait;
use Twig\Environment;

class AppVariable
{
    use ProxyTrait;

    public function __construct(\Symfony\Bridge\Twig\AppVariable $appVariable, BaseSettings $baseSettings, Referrer $referrer, Environment $twig)
    {
        $this->baseSettings = $baseSettings;
        $this->referrer     = $referrer;
        $this->twig         = $twig;

        $this->setProxy($appVariable);
    }

    public function settings() { return $this->baseSettings->get("app.settings") ?? []; }
    public function referrer() { return $this->referrer; }

    public function getGlobals() {

        return array_transforms(
            fn($k,$v):?array => $k != "app" && str_starts_with($k, "app") ? [str_strip($k, "app."), $v] : null, 
            $this->twig->getGlobals()); 
    }
}
