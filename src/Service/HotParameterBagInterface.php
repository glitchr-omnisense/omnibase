<?php

namespace Base\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 *
 */
interface HotParameterBagInterface extends ParameterBagInterface
{
    /**
     * @param array $parameters
     * @return mixed
     */
    public function add(array $parameters);

    public function get(string $path = "", ?array $bag = null, bool $useHotBag = true): array|bool|string|int|float|null;
    public function has(string $path, ?array $bag = null, bool $useHotBag = true): bool;

    /**
     * @param bool $ready
     * @return mixed
     */
    public function markAsReady(bool $ready = true);

    public function isReady(): bool;
}
