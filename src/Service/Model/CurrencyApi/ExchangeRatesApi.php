<?php

namespace Base\Service\Model\CurrencyApi;

use Base\Service\Model\CurrencyApi\Abstract\AbstractCurrencyApi;
use Base\Service\Settings;

// Endpoint http://api.exchangeratesapi.io/v1/
class ExchangeRatesApi extends AbstractCurrencyApi
{
    public static function getName(): string { return "exchange_rates_api"; }

    public function getOptions(): array
    {
        return ['access_key' => $this->getKey()];
    }
}