<?php

namespace Base\Service;

interface LocaleProviderInterface
{
    public static function __toLocale (string $locale, ?string $separator = LocaleProvider::SEPARATOR): string;
    public static function __toLang (string $locale): string;
    public static function __toCountry (string $locale): string;

    public function getLocale(?string $locale = null): string;
    public function setLocale(string $locale);
    public function compatibleLocale(string $locale, string $preferredLocale, ?array $availableLocales = null): ?string;

    public function getLang(?string $locale = null): string;
    public function getLangName(?string $locale = null): ?string;
    public function getCountry(?string $locale = null): string;
    public function getCountryName(?string $locale = null): string;
   
    public static function getDefaultLang(): ?string;
    public static function getFallbackLangs(): array;
    public static function getAvailableLangs(): array;
    
    public static function getDefaultCountry(): ?string;  
    public static function getFallbackCountries(): array; 
    public static function getAvailableCountries(): array;
    
    public static function getDefaultLocale(): ?string;
    public static function getFallbackLocales(): array;
    public static function getAvailableLocales(): array;

    public static function normalize(string|array $locale, string $separator = LocaleProvider::SEPARATOR): string|array;
}