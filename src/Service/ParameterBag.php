<?php

namespace Base\Service;

use Base\Traits\BagTrait;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBag;

class ParameterBag extends ContainerBag implements ParameterBagInterface, ContainerInterface
{
    use BagTrait;

    protected ?array $normalizedAll = null;
    public function normalizeAll(): array
    {
        $this->normalizedAll = $this->normalizedAll ?? $this->normalize(null, parent::all());
        return $this->normalizedAll;
    }

    public function get(string $path = "", ?array $bag = null): array|bool|string|int|float|null
    {
        if(array_key_exists($path, $bag ?? [])) 
            return $bag[$path];

        if(!$bag) $bag = $this->normalizeAll();
        else $bag = $this->normalize(null, $bag);

        return $this->read($path, $bag);
    }

    function set($path, $value, array &$bag = null)
    {
        $this->write($path, $value, $bag);
    }
}