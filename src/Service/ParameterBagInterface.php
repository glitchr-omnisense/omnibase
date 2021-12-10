<?php

namespace Base\Service;

interface ParameterBagInterface extends \Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface
{
    public function get(string $key = "", ?array $bag = null): array|bool|string|int|float|null;
    public function set(string $path, $value, ?array &$bag = null);
}
