<?php

namespace Base\Service;

use Base\Traits\BagTrait;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBag;

class ParameterBag extends ContainerBag implements ParameterBagInterface
{
    use BagTrait;

    #[SentitiveParameter]
    protected ?array $normalizedAll = null;

    public function normalizeAll(): array
    {
        $this->normalizedAll = $this->normalizedAll ?? $this->normalize(null, parent::all());
        return $this->normalizedAll;
    }

    public function get(string $path = "", ?array $bag = null): array|bool|string|int|float|null
    {
        $bag = array_replace_recursive($this->normalizeAll(), $this->normalize(null, $bag ?? []));
        return $this->read($path, $bag);
    }

    public function set($path, $value, array &$bag = null)
    {
        $this->write($path, $value, $bag);
    }
}
