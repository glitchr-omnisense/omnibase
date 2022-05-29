<?php

namespace Base\Database;

interface TranslatableInterface
{
    public function translate(?string $locale);

    public function getTranslations();
    public function removeTranslation(TranslationInterface $translation);
    public function addTranslation(TranslationInterface $translation);
}
