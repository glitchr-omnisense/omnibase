<?php

namespace Base\Service;

use UnitEnum;

interface ParameterBagInterface extends \Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface
{
    public function get(string $key = "", ?array $bag = null): array|bool|string|int|float|UnitEnum|null;
    public function set(string $path, array|bool|string|int|float|UnitEnum|null $value, ?array &$bag = null);
}
