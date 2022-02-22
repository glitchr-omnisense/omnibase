<?php

namespace Base\Model\CurrencyApi;

use Base\Model\CurrencyApi\Abstract\AbstractCurrencyApi;
use Base\Service\BaseSettings;

// Endpoint http://api.exchangeratesapi.io/v1/
class ExchangeRatesApi extends AbstractCurrencyApi
{
    public static function getName(): string { return "exchange_rates_api"; }

    public function getOptions(): array
    {
        return ['access_key' => $this->getKey()];
    }
}