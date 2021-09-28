<?php

namespace Base\Database;

interface TranslationInterface
{
    public function getTranslatable(): TranslatableInterface;
    public function setTranslatable(TranslatableInterface $translatable);

    public function getLocale(): string;
    public function setLocale(string $locale);
}
