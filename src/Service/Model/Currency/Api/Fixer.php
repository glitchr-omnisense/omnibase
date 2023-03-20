<?php

namespace Base\Service\Model\Currency\Api;

use Base\Service\Model\Currency\AbstractCurrencyApi;

// Fixer.io
class Fixer extends AbstractCurrencyApi
{
    public function getOptions(): array
    {
        return ['access_key' => $this->getKey()];
    }
}
