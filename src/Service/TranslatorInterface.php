<?php

namespace Base\Service;

use Symfony\Component\Translation\TranslatableMessage;
use \Symfony\Contracts\Translation\TranslatorInterface as SymfonyTranslatorInterface;
use Symfony\Component\Translation\TranslatorBagInterface as SymfonyTranslatorBagInterface;

interface TranslatorInterface extends SymfonyTranslatorInterface, SymfonyTranslatorBagInterface
{
    /**
     * The noun of an entity or an enumeration's value, singular or plural
     * (transEntity(), transEnum()). Declared here, not on Translator, so that
     * the code asking for a noun - an enumeration's type, Base\Database\Type\
     * EnumType::getText() - loads no class of the framework (Translator
     * imports the kernel). Translator::NOUN_SINGULAR still names them.
     */
    public const TRANSLATION_NOUN = "noun";
    public const NOUN_SINGULAR = "_singular";
    public const NOUN_PLURAL = "_plural";

    public function getFallbackLocales(): array;

    /**
     * @param string $locale
     * @return mixed
     */
    public function setLocale(string $locale);

    public function trans(TranslatableMessage|string $id, array $parameters = [], ?string $domain = null, ?string $locale = null, bool $recursive = true): string;

    public function transQuiet(TranslatableMessage|string $id, array $parameters = [], ?string $domain = null, ?string $locale = null, bool $recursive = true): ?string;

    public function transExists(TranslatableMessage|string $id, ?string $domain = null, ?string $locale = null): bool;

    public function transTime(int $time): string;

    public function transEnum(?string $value, string $class, string|array $options = self::NOUN_SINGULAR): ?string;

    public function transEnumExists(string $value, string $class, string|array $options = self::NOUN_SINGULAR): bool;

    public function transEntity(mixed $entityOrClassName, ?string $property = null, string|array $options = self::NOUN_SINGULAR): ?string;

    public function transEntityExists(mixed $entityOrClassName, ?string $property = null, string|array $options = self::NOUN_SINGULAR): bool;

    public function transRoute(string $routeName, ?string $domain = null): ?string;

    public function transRouteExists(string $routeName, ?string $domain = null): bool;
}
