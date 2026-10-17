<?php

namespace Base\Service;

use Base\Traits\BagTrait;
use UnitEnum;

/**
 * The application's parameters given as an array, by their full names
 * ("base.attributes.use_custom" => true), read by a dotted path as
 * Base\Service\ParameterBag reads the container's: omnibase's
 * ParameterBagInterface where there is no container (an entity manager built
 * by hand, Base\Bridge\Doctrine\Mapping). A path names a value or the values
 * under it ("base.attributes" is ['use_custom' => true, ...]).
 */
class ArrayParameterBag implements ParameterBagInterface
{
    use BagTrait;

    private ?array $normalized = null;

    /** @param array<string, mixed> $parameters */
    public function __construct(private array $parameters = [])
    {
    }

    public function get(string $key = "", ?array $bag = null): array|bool|string|int|float|UnitEnum|null
    {
        $this->normalized ??= $this->normalize(null, $this->parameters);
        $values = $bag === null ? $this->normalized : array_replace_recursive($this->normalized, $this->normalize(null, $bag));

        return $this->read($key, $values);
    }

    public function set(string $path, array|bool|string|int|float|UnitEnum|null $value, ?array &$bag = null): void
    {
        if ($bag !== null) {
            $this->write($path, $value, $bag);

            return;
        }

        $this->parameters[$path] = $value;
        $this->normalized = null;
    }

    public function has(string $path, ?array $bag = null): bool
    {
        return !empty($this->get($path, $bag));
    }

    public function all(): array
    {
        return $this->parameters;
    }
}
