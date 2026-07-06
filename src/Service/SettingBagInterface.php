<?php

namespace Base\Service;

interface SettingBagInterface
{
    public function clearAll();

    public function all(?string $locale = null): array;

    public function get(null|string|array $path = null, ?string $locale = null): array;

    public function getScalar(null|string|array $path, ?string $locale = null): mixed;

    /**
     * Flat [bagParameterName => resolvedDefaultLocaleValue] map for every
     * bag-linked setting, used by HotParameterBagSubscriber to seed the
     * HotParameterBag without querying/hydrating entities on every request.
     */
    public function getBagParameters(): array;

    /**
     * @param string $path
     * @param $value
     * @param string|null $locale
     * @return mixed
     */
    public function set(string $path, $value, ?string $locale = null);
}
