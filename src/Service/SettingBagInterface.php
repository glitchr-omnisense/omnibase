<?php

namespace Base\Service;

/**
 *
 */
interface SettingBagInterface
{
    public function all(?string $locale = null): array;

    public function get(null|string|array $path = null, ?string $locale = null): array;

    public function getScalar(null|string|array $path, ?string $locale = null): mixed;

    /**
     * @param string $path
     * @param $value
     * @param string|null $locale
     * @return mixed
     */
    public function set(string $path, $value, ?string $locale = null);
}
