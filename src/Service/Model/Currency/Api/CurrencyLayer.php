<?php

namespace Base\Service\Model\Currency\Api;

use Base\Service\Model\Currency\AbstractCurrencyApi;

// Endpoint : https://currencylayer.com/
class CurrencyLayer extends AbstractCurrencyApi
{
    protected bool $enterprise;
    public function __construct(Settings $settings, bool $enterprise = false)
    {
        parent::__construct($settings);
        $this->enterprise = $enterprise;
    }

    public static function getName(): string { return "currency_layer"; }

    public function getOptions(): array
    {
        return ['api_key' => $this->getKey(), 'enterprise' => $this->enterprise];
    }
}