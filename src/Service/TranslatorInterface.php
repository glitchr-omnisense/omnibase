<?php

namespace Base\Service;

interface TranslatorInterface extends \Symfony\Contracts\Translation\TranslatorInterface
{
    public function trans(?string $id, array $parameters = array(), ?string $domain = null, ?string $locale = null, bool $recursive = true):string;
    public function setLocale(string $locale);
    public function parseClass($class, string $parseBy = Translator::PARSE_NAMESPACE): string;
    
    public function time(int $time): string;
    public function enum(string $value, string $class, string $noun = Translator::TRANSLATION_SINGULAR): ?string;
    public function entity($class, string $noun = Translator::TRANSLATION_SINGULAR): ?string;
}