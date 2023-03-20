<?php

namespace Base\Service\Model\Currency;

use Base\Service\SettingBag;

abstract class AbstractCurrencyApi implements CurrencyApiInterface
{
    /**
     * @var SettingBag
     */
    protected $settingBag;

    protected ?string $key;
    public function __construct(SettingBag $settingBag)
    {
        $this->settingBag = $settingBag;
        $this->key = null;
    }

    public static function getName(): string
    {
        return camel2snake(class_basename(static::class));
    }
    public function supports(string $key): bool
    {
        return $this->key === $key;
    }

    protected int $priority = 0;
    public function getPriority(): int
    {
        return $this->priority;
    }

    protected array $options;
    public function getOptions(): array
    {
        return $this->options;
    }

    public function getKey(): ?string
    {
        if ($this->key === null) {
            $this->key = $this->settingBag->getScalar("api.currency.".self::getName());
        }

        return $this->key;
    }
}
