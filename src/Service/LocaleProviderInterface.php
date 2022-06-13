<?php

namespace Base\Service;

interface LocaleProviderInterface
{
    public function getLocale(?string $locale = null): string;
    public function setLocale(string $locale);

    public function getLang(?string $locale = null): string;
    public function getCountry(?string $locale = null): string;
    public function getCountryName(?string $locale = null): string;

    public static function getDefaultLocale(): ?string;
    public static function getFallbackLocales(): array;
    public static function getAvailableLocales(): array;

    public static function normalize(string|array $locale, string $separator = LocaleProvider::SEPARATOR): string|array;
}