<?php

namespace Base\Service;

interface LocaleProviderInterface
{
    public static function __toLocale (string $locale, ?string $separator = LocaleProvider::SEPARATOR): string;
    public static function __toLang (string $locale): string;
    public static function __toCountry (string $locale): string;
    
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