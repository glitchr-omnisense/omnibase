<?php

namespace Base\Model\CurrencyApi;

use Base\Model\CurrencyApi\Abstract\AbstractCurrencyApi;

// Fixer.io
class Fixer extends AbstractCurrencyApi
{
    public static function getName(): string { return "fixer"; }

    public function getOptions(): array
    {
        return ['access_key' => $this->getKey()];
    }
}