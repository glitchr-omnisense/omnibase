<?php

namespace Base\Database\Entity\Extension;

/**
 * Each implementation has, by TranslationTrait, the class it translates: the
 * schema (Base\Database\Schema\TranslationRule) asks it of the class itself.
 *
 * @method static string getTranslatableEntityClass()
 */
interface TranslationInterface
{
    public function getTranslatable(): ?TranslatableInterface;

    public function setTranslatable(?TranslatableInterface $translatable);

    public function getLocale(): ?string;

    public function setLocale(string $locale);

    public function isEmpty(): bool;
}
