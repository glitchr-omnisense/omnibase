<?php

namespace Base\Service;

interface LocaleProviderInterface
{
    public function getLocale(): ?string;
    public static function getDefaultLocale(): ?string;
    public static function getFallbackLocales(): array;
    public static function getAvailableLocales(): array;
}