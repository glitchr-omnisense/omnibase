<?php

namespace Base\Service;

use Base\Traits\BagTrait;
use RuntimeException;
use UnitEnum;

/**
 * The settings stored in the database laid over any parameters (plan "omnibase
 * agnostique", C9): the "hot" overlay of HotParameterBag, framework-free. A
 * setting linked to a parameter ("bag" in the back office) is given by add()
 * (under Symfony HotParameterBagSubscriber, on the first request or command,
 * from the SettingBag) and read in place of the configuration's value.
 *
 * get() reads, in this order, and by the presence of a key, not by its truth:
 * 1. the $bag given to the call,
 * 2. the hot settings (when the overlay is on and $useHotBag is true),
 * 3. the configuration (the parameters it is laid over).
 * The first that has the path answers - a setting stored as false, 0 or ""
 * wins over the configuration's true; a value of null is no value, the next
 * source is read. A path naming values under it ("base.user") gives them all,
 * each from the first source that has it. has() reads the same sources in the
 * same order.
 *
 * The overlay is on when the configuration says so (base.parameter_bag.use_hot_bag:
 * true) or when it is built on. Off, get() and has() read the $bag given and the
 * configuration: the parameters are always there.
 *
 * It replaces HotParameterBag's former logic, which chained the sources on
 * their truth (a false setting gave way to the configuration's value), read
 * nothing at all when use_hot_bag was not true (every parameter null unless a
 * $bag was given), took its settings in through the exception the frozen bag's
 * add() threw, and ordered has() differently from get().
 */
class HotParameters implements ParameterBagInterface
{
    use BagTrait;

    public const HOT_N_READY = false; // Debugging: refuse a read before the settings are in

    private bool $isReady = false;

    /** @var array<string, mixed> the settings, by the parameter's full name */
    protected array $hotBag = [];

    private ?array $normalizedHot = null;

    public function __construct(
        protected readonly ParameterBagInterface $config,
        private ?bool $enabled = null,
    ) {
    }

    /** Whether the settings are laid over the configuration: base.parameter_bag.use_hot_bag, unless built with a choice. */
    public function isEnabled(): bool
    {
        return $this->enabled ??= true === $this->config->get('base.parameter_bag.use_hot_bag');
    }

    /** The settings given, over those given before (a value of null takes one back). */
    public function add(array $parameters): void
    {
        $this->hotBag = array_merge($this->hotBag, $parameters);
        $this->normalizedHot = null;
    }

    public function all(): array
    {
        $all = $this->config->all();
        if ($this->isEnabled()) {
            $all = array_merge($all, array_filter($this->hotBag, static fn ($value) => null !== $value));
        }

        return $all;
    }

    public function get(string $path = "", ?array $bag = null, bool $useHotBag = true): array|bool|string|int|float|UnitEnum|null
    {
        $useHotBag = $useHotBag && $this->isEnabled();
        if ($useHotBag && self::HOT_N_READY && !$this->isReady) {
            throw new RuntimeException("Parameter bag is not Hot'N'Ready. (Did you call it before its related subscriber ?)");
        }

        // Lowest first: the configuration, the settings, the $bag given.
        $value = $this->config->get($path);
        if ($useHotBag && $this->hotBag) {
            $value = self::over($value, self::read($path, $this->normalizedHot ??= self::normalize(null, self::present($this->hotBag))));
        }
        if ($bag) {
            $value = self::over($value, self::read($path, self::normalize(null, self::present($bag))));
        }

        return $value;
    }

    public function has(string $path, ?array $bag = null, bool $useHotBag = true): bool
    {
        return null !== $this->get($path, $bag, $useHotBag);
    }

    /**
     * Writes into the $bag given, as the configuration's set() does.
     *
     * @param array|null $bag
     */
    public function set(string $path, array|bool|string|int|float|UnitEnum|null $value, ?array &$bag = null): void
    {
        $this->config->set($path, $value, $bag);
    }

    public function isReady(): bool
    {
        return $this->isReady;
    }

    /** @return $this */
    public function markAsReady(bool $ready = true)
    {
        $this->isReady = $ready;

        return $this;
    }

    /** The source above wins; values under a path are merged, key by key. */
    private static function over(mixed $below, mixed $above): mixed
    {
        if (null === $above) {
            return $below;
        }

        return is_array($below) && is_array($above) ? array_replace_recursive($below, $above) : $above;
    }

    /** @return array<string, mixed> the entries that have a value */
    private static function present(array $parameters): array
    {
        return array_filter($parameters, static fn ($value) => null !== $value);
    }
}
