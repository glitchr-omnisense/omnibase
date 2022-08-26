<?php

namespace Base\Service\Model\CurrencyApi;

use Base\Service\Model\CurrencyApi\Abstract\AbstractCurrencyApi;

// Fixer.io
class Fixer extends AbstractCurrencyApi
{
    public static function getName(): string { return "fixer"; }

    public function getOptions(): array
    {
        return ['access_key' => $this->getKey()];
    }
}