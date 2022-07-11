<?php

namespace Base\Service;

interface SettingBagInterface
{
    public function all        (?string $locale = null) : array;

    public function get(null|string|array $path = null, ?string $locale = null): array;
    public function getScalar(null|string|array $path, ?string $locale = null): string|array|object|null;

    public function set(string $path, $value, ?string $locale = null);

}
