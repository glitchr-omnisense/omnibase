<?php

namespace Base\Database;

interface TranslatableInterface
{
    public function getLocale(): string;
    public function setLocale(string $locale);

    public function getFallbackLocales(): array;
    public function setFallbackLocales(array $fallbackLocales);

    public function getDefaultLocale(): string;
    public function setDefaultLocale(string $defaultLocale);

    public function translate(?string $locale);
}
