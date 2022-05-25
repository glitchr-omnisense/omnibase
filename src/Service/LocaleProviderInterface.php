<?php

namespace Base\Service;

interface LocaleProviderInterface
{
    public function getLocale(?string $locale = null): string;
    public function setLocale(string $locale);

    public static function getDefaultLocale(): ?string;
    public static function getFallbackLocales(): array;
    public static function getAvailableLocales(): array;
}