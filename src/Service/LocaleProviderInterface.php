<?php

namespace Base\Service;

interface LocaleProviderInterface
{
    public function getLocale(): ?string;
    public function getDefaultLocale(): ?string;
    public function getFallbackLocales(): array;
}