<?php

namespace Base\Service;

use UnitEnum;

/**
 * The parameters of the application, read by a dotted path ("base.uploader.storage"),
 * as omnibase's code asks for them: the core's contract, with no class of the
 * framework (plan "omnibase agnostique", step C3c).
 *
 * Under Symfony it is the container's parameters: Base\Service\ParameterBag (and
 * HotParameterBag, with the settings stored in the database) is the adapter - it
 * extends DependencyInjection's ContainerBag, decorates `parameter_bag` and so
 * is DependencyInjection's ParameterBagInterface as well, its other methods
 * (resolveValue(), add()...) there for the code that types that one. This
 * interface no longer extends it: a class that reads the parameters through
 * omnibase's contract (AbstractAttribute, AttributeReader) loads nothing of the
 * framework.
 */
interface ParameterBagInterface
{
    public function get(string $key = "", ?array $bag = null): array|bool|string|int|float|UnitEnum|null;

    /**
     * @param string $path
     * @param array|bool|string|int|float|UnitEnum|null $value
     * @param array|null $bag
     * @return mixed
     */
    public function set(string $path, array|bool|string|int|float|UnitEnum|null $value, ?array &$bag = null): void;

    public function has(string $path, ?array $bag = null): bool;

    /** Every parameter, by its full name. */
    public function all(): array;
}
