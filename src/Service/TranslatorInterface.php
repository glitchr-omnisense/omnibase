<?php

namespace Base\Service;

interface TranslatorInterface extends \Symfony\Contracts\Translation\TranslatorInterface
{
    public function setLocale(string $locale);
    public function time(int $time): string;
    public function trans(?string $id, array $parameters = array(), ?string $domain = null, ?string $locale = null, bool $recursive = true):string;
}