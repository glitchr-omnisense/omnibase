<?php

namespace Base\Database\Entity\Extension;

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
