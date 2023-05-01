<?php

namespace Base\Service\Model\Currency\Api;

use Base\Service\Model\Currency\AbstractCurrencyApi;

// Endpoint: https://exchange-rates.abstractapi.com

class AbstractApi extends AbstractCurrencyApi
{
    public function getOptions(): array
    {
        return ['api_key' => $this->getKey()];
    }
}
