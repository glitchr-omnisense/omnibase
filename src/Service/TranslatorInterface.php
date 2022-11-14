<?php

namespace Base\Service;

use Symfony\Component\Translation\TranslatableMessage;

interface TranslatorInterface extends \Symfony\Contracts\Translation\TranslatorInterface
{
    public function getFallbackLocales(): array;
    public function setLocale(string $locale);

    public function trans      (TranslatableMessage|string $id, array $parameters = [], ?string $domain = null, ?string $locale = null, bool $recursive = true):string;
    public function transQuiet (TranslatableMessage|string $id, array $parameters = [], ?string $domain = null, ?string $locale = null, bool $recursive = true):?string;
    public function transExists(TranslatableMessage|string $id,                         ?string $domain = null, ?string $locale = null):bool;

    public function transTime(int $time): string;

    public function transEnum(?string $value, string $class, string|array $options = Translator::NOUN_SINGULAR): ?string;
    public function transEnumExists(string $value, string $class, string|array $options = Translator::NOUN_SINGULAR): bool;

    public function transEntity(mixed $entityOrClassName, ?string $property = null, string|array $options = Translator::NOUN_SINGULAR): ?string;
    public function transEntityExists(mixed $entityOrClassName, ?string $property = null, string|array $options = Translator::NOUN_SINGULAR): bool;

    public function transRoute(string $routeName, ?string $domain = null): ?string;
    public function transRouteExists(string $routeName, ?string $domain = null): bool;
}
