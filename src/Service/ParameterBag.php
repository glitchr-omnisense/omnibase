<?php

namespace Base\Service;

use Base\Traits\BagTrait;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBag;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface as SymfonyParameterBagInterface;

/**
 * The Symfony adapter of omnibase's Base\Service\ParameterBagInterface: the
 * container's parameters (it decorates `parameter_bag`), read by a dotted path,
 * and DependencyInjection's ParameterBagInterface and ContainerBagInterface as
 * well, for the code that types those.
 *
 * It wraps Symfony's ContainerBag rather than extending it (since 4.0, plan
 * "omnibase agnostique", P3): ContainerBag is a FrozenParameterBag, whose set()
 * and add() return never since DependencyInjection 7, and this class's set()
 * - HotParameterBag's add() - return void, which PHP refuses in a subclass;
 * glitchr/base-plugin patched DependencyInjection for it. What the frozen bag
 * forbade, this one forbids the same way (clear(), add(), remove() throw its
 * LogicException); what it read, it reads through the wrapped ContainerBag.
 */
class ParameterBag implements ParameterBagInterface, SymfonyParameterBagInterface, ContainerBagInterface
{
    use BagTrait;

    private readonly ContainerBag $containerBag;

    #[SentitiveParameter]
    protected ?array $normalizedAll = null;

    public function __construct(Container $container)
    {
        $this->containerBag = new ContainerBag($container);
    }

    public function all(): array
    {
        return $this->containerBag->all();
    }

    public function normalizeAll(): array
    {
        $this->normalizedAll = $this->normalizedAll ?? $this->normalize(null, $this->containerBag->all());
        return $this->normalizedAll;
    }

    public function get(string $path = "", ?array $bag = null): array|bool|string|int|float|null
    {
        $bag = array_replace_recursive($this->normalizeAll(), $this->normalize(null, $bag ?? []));
        return $this->read($path, $bag);
    }

    /**
     * @param $path
     * @param $value
     * @param array|null $bag
     * @return void
     **/
    public function set(string $path, $value, ?array &$bag = null): void
    {
        $this->write($path, $value, $bag);
    }

    public function has(string $path, ?array $bag = null): bool
    {
        $parameterBag = $this->get($path);
        return !empty($parameterBag);
    }

    public function clear(): void
    {
        throw new LogicException('Impossible to call clear() on a frozen ParameterBag.');
    }

    public function add(array $parameters): void
    {
        throw new LogicException('Impossible to call add() on a frozen ParameterBag.');
    }

    public function remove(string $name): void
    {
        throw new LogicException('Impossible to call remove() on a frozen ParameterBag.');
    }

    /** The container's parameters are resolved already. */
    public function resolve(): void
    {
    }

    public function resolveValue(mixed $value): mixed
    {
        return $this->containerBag->resolveValue($value);
    }

    public function escapeValue(mixed $value): mixed
    {
        return $this->containerBag->escapeValue($value);
    }

    public function unescapeValue(mixed $value): mixed
    {
        return $this->containerBag->unescapeValue($value);
    }
}
