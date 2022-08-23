<?php

namespace Base\Service\Model\CurrencyApi\Abstract;

use Base\Service\Model\CurrencyApiInterface;
use Base\Service\Settings;

abstract class AbstractCurrencyApi implements CurrencyApiInterface
{
    public function __construct(Settings $settings) { $this->settings = $settings; }
    public function supports(): bool { return $this->key !== null; }

    protected int $priority = 0;
    public function getPriority(): int { return $this->priority; }

    protected array $options;
    public function getOptions(): array { return $this->options; }

    protected string $key;
    public function getKey(): ?string
    {
        if ($this->key === null)
            $this->key = $this->settings->get("api.currency_api.".static::getName());

        return $this->key;
    }
}