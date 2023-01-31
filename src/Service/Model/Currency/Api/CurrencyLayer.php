<?php

namespace Base\Service\Model\Currency\Api;

use Base\Service\Model\Currency\AbstractCurrencyApi;
use Base\Service\SettingBagInterface;

// Endpoint : https://currencylayer.com/
class CurrencyLayer extends AbstractCurrencyApi
{
    protected bool $enterprise;
    public function __construct(SettingBagInterface $settings, bool $enterprise = false)
    {
        parent::__construct($settings);
        $this->enterprise = $enterprise;
    }

    public function getOptions(): array
    {
        return ['access_key' => $this->getKey(), 'enterprise' => $this->enterprise];
    }
}