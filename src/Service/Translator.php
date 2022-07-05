<?php

namespace Base\Service;

use Base\Database\Type\SetType;

use Doctrine\DBAL\Types\Type;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Translation\TranslatableMessage;

class Translator implements TranslatorInterface
{
    public const PARSE_EXTENDS   = "extends";
    public const PARSE_NAMESPACE = "namespace";

    public const DOMAIN_DEFAULT  = "messages";
    public const DOMAIN_BACKEND  = "backoffice";
    public const DOMAIN_ENTITY   = "entities";
    public const DOMAIN_ENUM     = "enums";

    public const TRANSLATION_SINGULAR     = "_singular";
    public const TRANSLATION_PLURAL       = "_plural";
    public const TRANSLATION_FEMININE     = "_feminine";
    public const TRANSLATION_MASCULINE    = "_masculine";
    public const TRANSLATION_PROPERTIES   = "_properties";

    public function __construct(\Symfony\Contracts\Translation\TranslatorInterface $translator, KernelInterface $kernel, ParameterBagInterface $parameterBag)
    {
        $this->translator = $translator;
        $this->parameterBag = $parameterBag;
        $this->isDebug    = $kernel->isDebug();
    }

    public function getLocale(): string { return $this->translator->getLocale(); }
    public function setLocale(string $locale) { $this->translator->setLocale($locale); }
    public function getFallbackLocales(): array { return $this->translator->getFallbackLocales(); }

    public const STRUCTURE_DOT = "^[@a-zA-Z0-9_.]+[.]{1}[a-zA-Z0-9_]+$";
    public const STRUCTURE_DOTBRACKET = "\{[ ]*[@a-zA-Z0-9_.]+[.]{0,1}[a-zA-Z0-9_]+[ ]*\}";
    public function transQuiet(TranslatableMessage|string $id, array $parameters = array(), ?string $domain = null, ?string $locale = null, bool $recursive = true, bool $nullable = true): ?string
    {
        return $this->transExists($id, $domain, $locale) ? $this->trans($id, $parameters, $domain, $locale, $recursive) : ($nullable ? null : $id);
    }

    public function trans(TranslatableMessage|string $id, array $parameters = array(), ?string $domain = null, ?string $locale = null, bool $recursive = true):string
    {
        if(!$id) return $id;

        $domainFallback = null;
        if($id instanceof TranslatableMessage) {
            $domainFallback = $id->getDomain();
            $domain ??= $domainFallback;
            $parameters = array_merge($id->getParameters(), $parameters);
            $id = $id->getMessage();
        }

        $id = trim($id);
        $customId  = preg_match("/".self::STRUCTURE_DOT."|".self::STRUCTURE_DOTBRACKET."/", $id);
        $atBegin   = str_starts_with($id, "@");

        $domainFallback = $domainFallback && str_starts_with($domainFallback, "@") ? substr($domainFallback, 1) : ($domainFallback ?? null);
        $domain         = $domain         && str_starts_with($domain, "@")         ? substr($domain, 1)         : ($domain ?? null);
        if ($id && $customId) {

            $array  = explode(".", $id);
            if($atBegin) {
                $domain = substr(array_shift($array), 1);
                $id     = implode(".", $array);
            }

        } else if($recursive) { // Check if recursive dot structure

            $count = 0;
            $fn = fn($k) => $this->trans($id, $parameters, $domain, $locale, false);

            $ret = preg_replace_callback("/".self::STRUCTURE_DOT."|".self::STRUCTURE_DOTBRACKET."/", $fn, $id, -1, $count);
            if ($ret != $id) return $ret;

            $ret = $this->translator->trans($ret, $parameters, $domain, $locale);
            if(preg_match("/^{[a-zA-Z0-9]*}$/", $ret)) {
                $ret = $this->translator->trans($ret, $parameters, $domainFallback, $locale);
                if(preg_match("/^{[a-zA-Z0-9]*}$/", $ret)) return $id;
            }

            return $ret;
        }

        // Replace parameter between brackets
        $bracketList = ['{}', "[]", "%%"];
        foreach ($parameters as $key => $element) {

            $brackets = -1;
            if(is_numeric($key)) $brackets = $bracketList[0];
            else if(is_string($key)) {

                $pos = array_search($key[0].$key[strlen($key) - 1], $bracketList);
                if($pos !== false) continue; // already formatted
            }

            if ( preg_match("/^[a-zA-Z0-9_.]+$/", $key) && $brackets < 0 )
                $brackets = begin($bracketList);

            if($brackets < 0) continue;
            $leftBracket  = $brackets[0];
            $rightBracket = $brackets[1];

            $parameters[$leftBracket.trim($key, $leftBracket.$rightBracket." ").$rightBracket] = $element;

            unset($parameters[$key]);
        }

        // Call for translation with parameter bag variables
        $trans  = $this->translator->trans($id, $parameters, $domain, $locale);
        if(preg_match_all("/%([^%]*)%/", $trans, $matches)) {

            foreach($matches[1] ?? [] as $key) {

                if(($parameter = $this->parameterBag->get($key)))
                    $parameters["%".$key."%"] = $parameter;
            }
        }

        // Lookup for nested translations
        while($this->transExists($trans, $domain, $locale) && $recursive) {
            $trans = $this->trans($trans, $parameters, $domain, $locale, false);
        }

        if ($trans == $id) {

            while($this->transExists($trans, $domainFallback, $locale) && $recursive) {
                $trans = $this->trans($trans, $parameters, $domainFallback, $locale, false);
            }

            if(!$this->isDebug) {

                $trans ??= $this->transQuiet($id, $parameters, $domain, LocaleProvider::getDefaultLocale());
                $trans ??= $this->transQuiet($id, $parameters, $domainFallback, LocaleProvider::getDefaultLocale());
            }
        }

        if ($trans == $id && $customId)
            return ($domain && $atBegin ? "@".$domain.".".$id : $id);

        return trim($trans);
    }

    protected function parseClass($class, string $parseBy = self::PARSE_NAMESPACE) :string
    {
        switch($parseBy) {

            case self::PARSE_EXTENDS:
                $parent = class_exists($class) ? get_parent_class($class) : null;

                $class = class_basename($class);
                if($parent) $class .= ".".class_basename($parent);
                while(class_exists($parent) && ( $parent = get_parent_class($parent) ))
                    $class .= ".".class_basename($parent);

                return camel2snake($class);

            break;

            case self::PARSE_NAMESPACE:
            default: return camel2snake(implode(".", array_slice(explode("\\", $class), 2)));
        }
    }

    public function transExists(TranslatableMessage|string $id, ?string $domain = null, ?string $locale = null, bool $localeCountry = true):bool
    {
        $locale  ??= $this->getLocale();
        $catalogue = $this->translator->getCatalogue($localeCountry ? LocaleProvider::__toLocale($locale, "_") : LocaleProvider::__toLang($locale));
        if($id instanceof TranslatableMessage) {
            $domain ??= $id->getDomain();
            $id       = $id->getMessage();
        }

        $id = trim($id);
        $array  = explode(".", $id);
        if(str_starts_with($id, "@")) {
            $domain = substr(array_shift($array), 1);
            $id     = implode(".", $array);
        }

        $domain    = $domain && str_starts_with($domain, "@") ? substr($domain, 1) : ($domain ?? null);
        return $catalogue->has($id, $domain ?? self::DOMAIN_DEFAULT);
    }

    public function enum(?string $value, string $class, string|array $options = self::TRANSLATION_SINGULAR): ?string
    {
        $declaringClass = $class;
        while(( count(array_filter($declaringClass::getPermittedValues(false), fn($c) => $c === $value)) == 0 )) {

            $declaringClass = get_parent_class($declaringClass);
            if($declaringClass === Type::class || $declaringClass === null) {
                $declaringClass = $class;
                break;
            }
        }

        $value = $value ? ".".$value : "";
        $offset = is_subclass_of($class, SetType::class) ? -3 : -2;
        $class  = $this->parseClass($declaringClass, self::PARSE_EXTENDS);
        $class  = implode(".", array_slice(explode(".",$class), 0, $offset));

        if(!is_array($options)) $options = [$options];

        $gender = null;
        if(in_array(self::TRANSLATION_MASCULINE, $options)) $gender = self::TRANSLATION_MASCULINE;
        else if(in_array(self::TRANSLATION_FEMININE, $options)) $gender = self::TRANSLATION_FEMININE;
        $gender = $gender ? ".".$gender  : "";

        $noun = null;
        if(in_array(self::TRANSLATION_PLURAL, $options)) $noun = self::TRANSLATION_PLURAL;
        else if(in_array(self::TRANSLATION_SINGULAR, $options)) $noun = self::TRANSLATION_SINGULAR;
        $noun = $noun     ? ".".$noun  : "";

        return $class ? mb_ucfirst($this->trans(mb_strtolower($class.$value.$noun), [], self::DOMAIN_ENUM)) : null;
    }

    public function enumExists(?string $value, string $class, string|array $options = self::TRANSLATION_SINGULAR): bool
    {
        $declaringClass = $class;
        while(( count(array_filter($declaringClass::getPermittedValues(false), fn($c) => $c === $value)) == 0 )) {

            $declaringClass = get_parent_class($declaringClass);
            if($declaringClass === Type::class || $declaringClass === null) {
                $declaringClass = $class;
                break;
            }
        }

        $value = $value ? ".".$value : "";
        $offset = is_subclass_of($class, SetType::class) ? -3 : -2;
        $class  = $this->parseClass($declaringClass, self::PARSE_EXTENDS);
        $class  = implode(".", array_slice(explode(".",$class), 0, $offset));

        if(!is_array($options)) $options = [$options];

        $gender = null;
        if(in_array(self::TRANSLATION_MASCULINE, $options)) $gender = self::TRANSLATION_MASCULINE;
        else if(in_array(self::TRANSLATION_FEMININE, $options)) $gender = self::TRANSLATION_FEMININE;
        $gender = $gender ? ".".$gender  : "";

        $noun = null;
        if(in_array(self::TRANSLATION_PLURAL, $options)) $noun = self::TRANSLATION_PLURAL;
        else if(in_array(self::TRANSLATION_SINGULAR, $options)) $noun = self::TRANSLATION_SINGULAR;
        $noun = $noun     ? ".".$noun  : "";

        return $class ? mb_ucfirst($this->transExists(mb_strtolower($class.$value.$noun), self::DOMAIN_ENUM)) : null;
    }

    public function route(string $routeName, string $domain = null): ?string
    {
        $domain = $domain ? $domain."." : "@controllers.";
        return $this->trans($domain.$routeName.".title");
    }

    public function routeExists(string $routeName, string $domain = null) : bool
    {
        $domain = $domain ? $domain."." : "@controllers.";
        return $this->transExists($domain.$routeName.".title");
    }

    public function entity(mixed $entityOrClassName, ?string $property = null, string|array $options = self::TRANSLATION_SINGULAR): ?string
    {
        if(!is_array($options)) $options = [$options];
        if(is_object($entityOrClassName)) $entityOrClassName = get_class($entityOrClassName);

        $gender = null;
        if(in_array(self::TRANSLATION_MASCULINE, $options)) $gender = self::TRANSLATION_MASCULINE;
        else if(in_array(self::TRANSLATION_FEMININE, $options)) $gender = self::TRANSLATION_FEMININE;
        $gender = $gender ? ".".$gender  : "";

        $noun = null;
        if(in_array(self::TRANSLATION_PLURAL, $options)) $noun = self::TRANSLATION_PLURAL;
        else if(in_array(self::TRANSLATION_SINGULAR, $options)) $noun = self::TRANSLATION_SINGULAR;
        $noun = $noun     ? ".".$noun  : "";

        $entityOrClassName = $this->parseClass($entityOrClassName, self::PARSE_NAMESPACE);
        $property = $property ? ".".$property : "";

        $trans ??= $this->transQuiet(mb_strtolower($entityOrClassName.$property.$gender.$noun), [], self::DOMAIN_ENTITY);
        $trans ??= $this->transQuiet(mb_strtolower($entityOrClassName.$property.$noun), [], self::DOMAIN_ENTITY);
        $trans ??= $this->transQuiet(mb_strtolower($entityOrClassName.$property.$gender), [], self::DOMAIN_ENTITY);
        $trans ??= $this->transQuiet(mb_strtolower($entityOrClassName.$property), [], self::DOMAIN_ENTITY);
        $trans ??=                   mb_strtolower($entityOrClassName.$property.$gender.$noun);

        return $entityOrClassName ? mb_ucfirst($trans) : null;
    }

    public function entityExists(mixed $entityOrClassName, ?string $property = null, string|array $options = self::TRANSLATION_SINGULAR): bool
    {
        if(!is_array($options)) $options = [$options];
        if(is_object($entityOrClassName)) $entityOrClassName = get_class($entityOrClassName);

        $gender = null;
        if(in_array(self::TRANSLATION_MASCULINE, $options)) $gender = self::TRANSLATION_MASCULINE;
        else if(in_array(self::TRANSLATION_FEMININE, $options)) $gender = self::TRANSLATION_FEMININE;
        $gender = $gender ? ".".$gender  : "";

        $noun = null;
        if(in_array(self::TRANSLATION_PLURAL, $options)) $noun = self::TRANSLATION_PLURAL;
        else if(in_array(self::TRANSLATION_SINGULAR, $options)) $noun = self::TRANSLATION_SINGULAR;
        $noun = $noun     ? ".".$noun  : "";

        $entityOrClassName = $this->parseClass($entityOrClassName, self::PARSE_NAMESPACE);
        $property = $property ? ".".$property : "";

        if($this->transQuiet(mb_strtolower($entityOrClassName.$property.$gender.$noun), [], self::DOMAIN_ENTITY)) return true;
        if($this->transQuiet(mb_strtolower($entityOrClassName.$property.$noun), [], self::DOMAIN_ENTITY)) return true;
        if($this->transQuiet(mb_strtolower($entityOrClassName.$property.$gender), [], self::DOMAIN_ENTITY)) return true;
        if($this->transQuiet(mb_strtolower($entityOrClassName.$property), [], self::DOMAIN_ENTITY)) return true;
        if($this->transQuiet(mb_strtolower($entityOrClassName.$gender), [], self::DOMAIN_ENTITY)) return true;

        return false;
    }
    
    public function time(int $time): string
    {
        if($time > 0) {

            $seconds = fmod  ($time, 60);
            $time    = intdiv($time, 60);
            $minutes = fmod  ($time, 60);
            $time    = intdiv($time, 60);
            $hours   = fmod  ($time, 24);
            $time    = intdiv($time, 24);
            $days    = fmod  ($time, 30);
            $time    = intdiv($time, 30);
            $months  = fmod  ($time, 12);
            $years   = intdiv($time, 12);

            return trim(
                $this->trans("base.years",   [$years])  ." ".
                $this->trans("base.months",  [$months]) ." ".
                $this->trans("base.days",    [$days])   ." ".
                $this->trans("base.hours",   [$hours])  ." ".
                $this->trans("base.minutes", [$minutes])." ".
                $this->trans("base.seconds", [$seconds])
            );
        }

        return "";
    }
}
