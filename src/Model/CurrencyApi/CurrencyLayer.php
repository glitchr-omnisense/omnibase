<?php

namespace Base\Model\CurrencyApi;

use Base\Model\CurrencyApi\Abstract\AbstractCurrencyApi;
use Base\Service\BaseService;
use Base\Service\BaseSettings;

// Endpoint : https://currencylayer.com/
class CurrencyLayer extends AbstractCurrencyApi
{
    protected bool $enterprise;
    public function __construct(BaseSettings $baseSettings, bool $enterprise = false)
    {
        parent::__construct($baseSettings);
        $this->enterprise = $enterprise;
    }

    public static function getName(): string { return "currency_layer"; }

    public function getOptions(): array
    {
        return ['api_key' => $this->getKey(), 'enterprise' => $this->enterprise];
    }
}