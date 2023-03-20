<?php

namespace Base\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

interface HotParameterBagInterface extends ParameterBagInterface
{
    public function add(array $parameters);
    public function get(string $key = "", array $bag = null, bool $useHotBag = true): array|bool|string|int|float|null;

    public function markAsReady(bool $ready = true);
    public function isReady(): bool;
}
