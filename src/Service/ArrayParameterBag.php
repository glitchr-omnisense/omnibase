<?php

namespace Base\Service;

use UnitEnum;

/**
 * The application's parameters given as an array, by their full names
 * ("base.attributes.use_custom" => true), read by a dotted path as
 * Base\Service\ParameterBag reads the container's (both are
 * Base\Service\DottedParameterBag): omnibase's ParameterBagInterface where
 * there is no container (an entity manager built by hand,
 * Base\Bridge\Doctrine\Mapping; another framework). A path names a value or
 * the values under it ("base.attributes" is ['use_custom' => true, ...]).
 */
class ArrayParameterBag extends DottedParameterBag
{
    /** @param array<string, mixed> $parameters */
    public function __construct(private array $parameters = [])
    {
    }

    protected function parameters(): array
    {
        return $this->parameters;
    }

    public function set(string $path, array|bool|string|int|float|UnitEnum|null $value, ?array &$bag = null): void
    {
        if ($bag !== null) {
            $this->write($path, $value, $bag);

            return;
        }

        $this->parameters[$path] = $value;
        $this->forget();
    }
}
