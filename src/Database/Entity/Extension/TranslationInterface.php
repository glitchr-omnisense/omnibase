<?php

namespace Base\Database\Entity\Extension;

interface TranslationInterface
{
    public function getTranslatable(): ?TranslatableInterface;

    public function setTranslatable(?TranslatableInterface $translatable);

    public function getLocale(): ?string;

    public function setLocale(string $locale);

    public function isEmpty(): bool;
}
