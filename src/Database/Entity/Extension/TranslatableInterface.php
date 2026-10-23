<?php

namespace Base\Database\Entity\Extension;

/**
 * Each implementation has, by TranslatableTrait, the class of its translations:
 * the schema (Base\Database\Schema\TranslationRule) asks it of the class itself.
 *
 * @method static string|null getTranslationEntityClass(bool $withInheritance = true, bool $selfClass = false)
 */
interface TranslatableInterface
{
    public function translate(?string $locale);

    public function getTranslations();
    public function clearTranslations();

    public function addTranslation(TranslationInterface $translation);
    public function removeTranslation(TranslationInterface $translation);
}
