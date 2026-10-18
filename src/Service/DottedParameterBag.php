<?php

namespace Base\Service;

use Base\Traits\BagTrait;
use UnitEnum;

/**
 * The application's parameters by their full names ("base.attributes.use_custom"
 * => true), read by a dotted path (plan "omnibase agnostique", C9): the
 * framework-free source both Base\Service\ArrayParameterBag (an array) and
 * Base\Service\ParameterBag (the Symfony container's parameters) read through.
 *
 * A path names a value or the values under it ("base.attributes" is
 * ['use_custom' => true, ...]); a $bag given to get() is laid over the
 * parameters, by key. has() is whether get() finds a value - a parameter set
 * to false, 0 or "" is there; one set to null is not.
 */
abstract class DottedParameterBag implements ParameterBagInterface
{
    use BagTrait;

    private ?array $normalized = null;

    /** @return array<string, mixed> every parameter, by its full name */
    abstract protected function parameters(): array;

    public function all(): array
    {
        return $this->parameters();
    }

    public function get(string $key = "", ?array $bag = null): array|bool|string|int|float|UnitEnum|null
    {
        $this->normalized ??= self::normalize(null, $this->parameters());
        $values = $bag ? array_replace_recursive($this->normalized, self::normalize(null, $bag)) : $this->normalized;

        return self::read($key, $values);
    }

    public function has(string $path, ?array $bag = null): bool
    {
        return null !== $this->get($path, $bag);
    }

    /** The parameters changed: read them again. */
    protected function forget(): void
    {
        $this->normalized = null;
    }
}
