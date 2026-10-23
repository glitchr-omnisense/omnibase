<?php

namespace Base\Service;

/**
 * The settings the administrator stored (layoutSetting), read through a
 * host's own reader where omnibase's SettingBag (its entity manager, cache,
 * localizer and Symfony parameters) is not built - omnibridge/laravel's
 * DatabaseSettings (plan "omniauth", step AU8b: SecurityPolicy under
 * Laravel). Read only: a setting is changed in the back office, which is the
 * Symfony site's.
 */
final class ReadSettings implements SettingBagInterface
{
    /** @param \Closure(string $path, ?string $locale): mixed $read a setting's value, null when none is stored */
    public function __construct(private readonly \Closure $read)
    {
    }

    /** @return void */
    public function clearAll()
    {
    }

    /** @return array<string, mixed> */
    public function all(?string $locale = null): array
    {
        return [];
    }

    /**
     * @param string|list<string>|null $path
     *
     * @return array<string, mixed>
     */
    public function get(null|string|array $path = null, ?string $locale = null): array
    {
        if (\is_array($path)) {
            $settings = [];
            foreach ($path as $one) {
                $settings[$one] = $this->get($one, $locale);
            }

            return $settings;
        }

        return null === $path ? [] : ['_self' => ($this->read)($path, $locale)];
    }

    /** @param string|list<string>|null $path */
    public function getScalar(null|string|array $path, ?string $locale = null): mixed
    {
        if (\is_array($path)) {
            return array_map(fn (string $one) => $this->getScalar($one, $locale), $path);
        }

        return null === $path ? null : ($this->read)($path, $locale);
    }

    /** @return array<string, mixed> */
    public function getBagParameters(): array
    {
        return [];
    }

    /**
     * @param mixed $value
     *
     * @return never
     */
    public function set(string $path, $value, ?string $locale = null)
    {
        throw new \LogicException(\sprintf('The settings are read only here ("%s"): the back office changes them.', $path));
    }
}
