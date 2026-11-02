<?php

namespace Base\Service;

use UnitEnum;

/**
 * The parameters with the settings stored in the database laid over them:
 * Base\Service\HotParameters (plan "omnibase agnostique", C9), which
 * `parameter_bag` is under Symfony (omnibridge/symfony's HotParameterBag, also
 * DependencyInjection's ParameterBagInterface) and the parameters under
 * omnibridge/laravel. Since step F5c of plan "omnibase agnostic" it extends
 * omnibase's ParameterBagInterface, no longer DependencyInjection's.
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
