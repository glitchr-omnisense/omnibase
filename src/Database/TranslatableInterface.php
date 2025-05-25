<?php

namespace Base\Database;

/**
 *
 */
interface TranslatableInterface
{
    public function translate(?string $locale);

    public function getTranslations();
    public function clearTranslations();

    public function addTranslation(TranslationInterface $translation);
    public function removeTranslation(TranslationInterface $translation);
}
