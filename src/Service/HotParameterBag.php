<?php

namespace Base\Service;

use RuntimeException;

class HotParameterBag extends ParameterBag implements HotParameterBagInterface
{
    public const HOT_N_READY = false; // Debugging
    private $isReady = false;

    protected $hotBag = [];
    public function add(array $parameters)
    {
        try {
            parent::add($parameters);
        } catch (\Exception $e) {
            $this->hotBag = array_merge($this->hotBag, $parameters);
        }

        return $this;
    }

    public function get(string $key = "", array $bag = null, bool $useHotBag = true): array|bool|string|int|float|null
    {
        if (!parent::get("base.parameter_bag.use_hot_bag") ?? false) {
            $useHotBag = false;
        } elseif (self::HOT_N_READY && !$this->isReady) {
            throw new RuntimeException("Parameter bag is not Hot'N'Ready. (Did you call it before its related subscriber ?)");
        }

        $hotParameter = $useHotBag ? parent::get($key, $this->hotBag) : null;
        return $hotParameter ?? parent::get($key, $bag);
    }

    public function isReady(): bool
    {
        return $this->isReady;
    }
    public function markAsReady(bool $ready = true)
    {
        $this->isReady = $ready;
        return $this;
    }
}
