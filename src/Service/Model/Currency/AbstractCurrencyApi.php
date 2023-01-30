<?php

namespace Base\Service\Model\Currency;

use Base\Service\SettingBag;

abstract class AbstractCurrencyApi implements CurrencyApiInterface
{
    public function __construct(SettingBag $settingBag) { $this->settingBag = $settingBag; }
    public function supports(string $key): bool { return $this->key === $key; }

    protected int $priority = 0;
    public function getPriority(): int { return $this->priority; }

    protected array $options;
    public function getOptions(): array { return $this->options; }

    protected string $key;
    public function getKey(): ?string
    {
        if ($this->key === null)
            $this->key = $this->settingBag->get("api.currency_api.".static::getName());

        return $this->key;
    }
}
