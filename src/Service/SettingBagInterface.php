<?php

namespace Base\Service;

use DateTime;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

interface SettingBagInterface
{
    public function get(null|string|array $path = null, ?string $locale = null): array;
    public function getScalar(null|string|array $path, ?string $locale = null): string|array|object|null;

    public function set(string $path, $value, ?string $locale = null);

    public function maintenance(?string $locale = null) : bool;
    public function all        (?string $locale = null) : array;
    public function scheme     (?string $locale = null) : string;
    public function base_dir   (?string $locale = null) : string;
    public function url        (?string $path = null, ?string $packageName = null, int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH);
    public function host       (int $level = 0, ?string $locale = null) : ?string;
    public function birthdate  (?string $locale = null) : DateTime;
    public function age        (?string $locale = null) : string;
}
