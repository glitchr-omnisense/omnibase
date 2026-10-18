<?php

namespace Base\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use UnitEnum;

/**
 * The parameters with the settings stored in the database laid over them, as
 * DependencyInjection's ParameterBagInterface: what `parameter_bag` is under
 * Symfony (Base\Service\HotParameterBag over Base\Service\HotParameters, plan
 * "omnibase agnostique", C9).
 */
interface HotParameterBagInterface extends ParameterBagInterface
{
    /** The settings, by the parameter's full name, laid over the configuration. */
    public function add(array $parameters): void;

    public function get(string $path = "", ?array $bag = null, bool $useHotBag = true): array|bool|string|int|float|UnitEnum|null;

    public function has(string $path, ?array $bag = null, bool $useHotBag = true): bool;

    /**
     * @param bool $ready
     * @return mixed
     */
    public function markAsReady(bool $ready = true);

    public function isReady(): bool;
}
