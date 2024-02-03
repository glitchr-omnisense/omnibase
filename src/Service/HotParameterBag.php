<?php

namespace Base\Service;

use Exception;
use RuntimeException;

/**
 *
 */
class HotParameterBag extends ParameterBag implements HotParameterBagInterface
{
    public const HOT_N_READY = false; // Debugging
    private bool $isReady = false;

    #[SentitiveParameter]
    protected array $hotBag = [];

    /**
     * @param array $parameters
     * @return $this|void
     */
    /**
     * @param array $parameters
     * @return $this
     */
    public function add(array $parameters)
    {
        try {
            parent::add($parameters);
        } catch (Exception $e) {
            $this->hotBag = array_merge($this->hotBag, $parameters);
        }

        return $this;
    }

    public function all(): array
    {
        return array_merge(parent::all(), $this->hotBag);
    }

    public function has(string $path, ?array &$bag = null, bool $useHotBag = true): bool
    {
        if($bag && parent::has($path, $bag))
            return true;
        if($useHotBag && parent::has($path, $this->hotBag))
            return true;

        return parent::has($path);
    }

    public function get(string $path = "", ?array &$bag = null, bool $useHotBag = true): array|bool|string|int|float|null
    {
        if (!parent::get("base.parameter_bag.use_hot_bag") ?? false) {
            $useHotBag = false;
        } elseif (self::HOT_N_READY && !$this->isReady) {
            throw new RuntimeException("Parameter bag is not Hot'N'Ready. (Did you call it before its related subscriber ?)");
        }

        $parameterBag = null;
        if(!$parameterBag && $bag) 
            $parameterBag = parent::get($path, $bag);
        if(!$parameterBag && $useHotBag)
            $parameterBag = parent::get($path, $this->hotBag);
        if(!$parameterBag && $useHotBag)
            $parameterBag = parent::get($path);

        return $parameterBag;
    }

    public function isReady(): bool
    {
        return $this->isReady;
    }

    /**
     * @param bool $ready
     * @return $this
     */
    public function markAsReady(bool $ready = true)
    {
        $this->isReady = $ready;
        return $this;
    }
}
