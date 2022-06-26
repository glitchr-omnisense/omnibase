<?php

namespace Base\Service;

use Symfony\Component\Translation\TranslatableMessage;

interface TranslatorInterface extends \Symfony\Contracts\Translation\TranslatorInterface
{
    public function setLocale(string $locale);

    public function trans      (TranslatableMessage|string $id, array $parameters = [], ?string $domain = null, ?string $locale = null, bool $recursive = true):string;
    public function transQuiet (TranslatableMessage|string $id, array $parameters = [], ?string $domain = null, ?string $locale = null, bool $recursive = true):?string;
    public function transExists(TranslatableMessage|string $id,                         ?string $domain = null, ?string $locale = null):bool;

    public function time(int $time): string;

    public function enum(?string $value, string $class, string|array $options = Translator::TRANSLATION_SINGULAR): ?string;
    public function enumExists(?string $value, string $class, string|array $options = Translator::TRANSLATION_SINGULAR): bool;

    public function entity(mixed $entityOrClassName, string $property, string|array $options = Translator::TRANSLATION_SINGULAR): ?string;
    public function entityExists(mixed $entityOrClassName, string $property, string|array $options = Translator::TRANSLATION_SINGULAR): bool;

    public function route(string $routeName, ?string $domain = null): ?string;
    public function routeExists(string $routeName, ?string $domain = null): bool;
}