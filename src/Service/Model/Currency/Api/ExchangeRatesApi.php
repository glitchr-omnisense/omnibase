<?php

namespace Base\Service\Model\Currency\Api;

use Base\Service\Model\Currency\AbstractCurrencyApi;

// Endpoint http://api.exchangeratesapi.io/v1/
class ExchangeRatesApi extends AbstractCurrencyApi
{
    public function getOptions(): array
    {
        return ['access_key' => $this->getKey()];
    }
}
