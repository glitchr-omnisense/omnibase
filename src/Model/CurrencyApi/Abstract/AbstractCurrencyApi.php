<?php

namespace Base\Model\CurrencyApi\Abstract;

use Base\Model\CurrencyApiInterface;
use Base\Service\BaseSettings;

abstract class AbstractCurrencyApi implements CurrencyApiInterface
{
    public function __construct(BaseSettings $baseSettings) { $this->baseSettings = $baseSettings; }
    public function supports(): bool { return $this->key !== null; }

    protected int $priority = 0;
    public function getPriority(): int { return $this->priority; }

    protected array $options;
    public function getOptions(): array { return $this->options; }

    protected string $key;
    public function getKey(): ?string 
    { 
        if ($this->key === null)
            $this->key = $this->baseSettings->get("api.currency_api.".static::getName());

        return $this->key;
    }
}