<?php

namespace Base\Twig;

use BadMethodCallException;
use Base\Service\BaseSettings;
use Base\Traits\ProxyTrait;

class AppVariable
{
    use ProxyTrait;

    public function __construct(\Symfony\Bridge\Twig\AppVariable $appVariable, BaseSettings $baseSettings)
    {
        $this->baseSettings = $baseSettings;
        $this->setProxy($appVariable);
    }

    public function settings()
    {
        return $this->baseSettings->get("app.settings") ?? [];
    }
}
