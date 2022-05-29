<?php

namespace Base\Service;

class HotParameterBag extends ParameterBag implements ParameterBagInterface
{
    protected $hotBag = [];
    public function add(array $parameters)
    {
        try { parent::add($parameters); }
        catch (\Exception $e) { $this->hotBag = array_merge($this->hotBag, $parameters); }

        return $this;
    }

    public function get(string $key = "", array $bag = null, bool $useHotBag = true): array|bool|string|int|float|null
    {
        $hotParameter = $useHotBag ? parent::get($key, $this->hotBag) : null;
        return $hotParameter ?? parent::get($key, $bag);
    }
}