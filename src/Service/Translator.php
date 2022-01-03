<?php

namespace Base\Service;

use Base\Traits\BaseTrait;
use Doctrine\DBAL\Types\Type;
use Symfony\Component\HttpKernel\KernelInterface;

class Translator implements TranslatorInterface
{
    public const PARSE_EXTENDS   = "extends";
    public const PARSE_NAMESPACE = "namespace";

    public const DOMAIN_ENTITY       = "entities";
    public const DOMAIN_ENUM         = "enums";

    public const TRANSLATION_SINGULAR     = "singular";
    public const TRANSLATION_PLURAL       = "plural";
    public const TRANSLATION_FEMININE     = "feminine";
    public const TRANSLATION_MASCULINE    = "masculine";

    public function __construct(\Symfony\Contracts\Translation\TranslatorInterface $translator, KernelInterface $kernel)
    {
        $this->translator = $translator;
        $this->isDebug    = $kernel->isDebug();
    }

    public function getLocale(): string { return $this->translator->getLocale(); }
    public function setLocale(string $locale) { $this->translator->setLocale($locale); }
    public function getFallbackLocales(): array { return $this->translator->getFallbackLocales(); }

    public const STRUCTURE_DOT = "^[@a-zA-Z0-9_.]+[.]{1}[a-zA-Z0-9_]+$";
    public const STRUCTURE_DOTBRACKET = "\{[ ]*[@a-zA-Z0-9_.]+[.]{0,1}[a-zA-Z0-9_]+[ ]*\}";
    public function trans(?string $id, array $parameters = array(), ?string $domain = null, ?string $locale = null, bool $recursive = true):string
    {
        if($id === null) return null;

        $id = trim($id);
        $customId  = preg_match("/".self::STRUCTURE_DOT."|".self::STRUCTURE_DOTBRACKET."/", $id);
        $atBegin   = str_starts_with($id, "@");

        $domain    = str_starts_with($domain, "@") ? substr($domain, 1) : $domain ?? null;
        if ($id && $customId) {

            $array  = explode(".", $id);
            if($atBegin) {
                $domain = substr(array_shift($array), 1);
                $id     = implode(".", $array);
            }

        } else if($recursive) { // Check if recursive dot structure

            $count = 0;
            $fn = function ($key) use ($id, $parameters, $domain, $locale) { return $this->trans($id, $parameters, $domain, $locale, false); };
            $ret = preg_replace_callback("/".self::STRUCTURE_DOT."|".self::STRUCTURE_DOTBRACKET."/", $fn, $id, -1, $count);

            return ($ret == $id ? $this->translator->trans($ret, $parameters, $domain, $locale) : $ret);
        }

        // Replace parameter between brackets
        $bracketList = ['{}s', "%%", "[]", "()"];
        foreach ($parameters as $key => $element) {

            $brackets = -1;
            if(is_numeric($key)) $brackets = $bracketList[0];
            else if(is_string($key)) {

                $pos = array_search($key[0].$key[strlen($key) - 1], $bracketList);
                if($pos !== false) $brackets = $bracketList[$pos];
            }

            if ($brackets < 0) continue;
            $leftBracket  = $brackets[0];
            $rightBracket = $brackets[1];

            $parameters[$leftBracket.((string) $key).$rightBracket] = $element; //htmlspecialchars($element);
            unset($parameters[$key]);
        }

        // Call for translation with custom parameters
        $trans = $this->translator->trans($id, $parameters, $domain, $locale);
        if ($trans == $id && !$this->isDebug)
            $trans = $this->translator->trans($id, $parameters, $domain, LocaleProvider::getDefaultLocale());

        if ($trans == $id && $customId)
            return ($domain && $atBegin ? "@".$domain.".".$id : $id);

        return trim($trans);
    }

    public function parseClass($class, string $parseBy = self::PARSE_NAMESPACE) :string
    {
        switch($parseBy) {

            case self::PARSE_EXTENDS:
                $parent = class_exists($class) ? get_parent_class($class) : null;

                $class = class_basename($class);
                if($parent) $class .= ".".class_basename($parent);
                while(class_exists($parent) && ( $parent = get_parent_class($parent) ))
                    $class .= ".".class_basename($parent);

                return camel_to_snake($class);

            break;

            case self::PARSE_NAMESPACE:
            default: return camel_to_snake(implode(".", array_slice(explode("\\", $class), 2)));
        }
    }

    public function enum(?string $value, $class, string $noun = self::TRANSLATION_SINGULAR): string
    {
        $declaringClass = $class;
        while(( count(array_filter($declaringClass::getPermittedValues(false), fn($c) => $c === $value)) == 0 )) {

            $declaringClass = get_parent_class($declaringClass);
            if($declaringClass === Type::class || $declaringClass === null) {
                $declaringClass = $class;
                break;
            }
        }

        $class  = $this->parseClass($declaringClass, self::PARSE_EXTENDS);
        $class  = implode(".", array_slice(explode(".",$class), 0, -2));
        $value = !empty($value) ? ".".$value : $value;
        $noun  = !empty($noun)  ? ".".$noun  : $noun;

        return mb_ucfirst($this->trans(mb_strtolower($class.$value.$noun), [], self::DOMAIN_ENUM));
    }

    public function entity($class, string $noun = self::TRANSLATION_SINGULAR): string
    {
        $class = $this->parseClass($class, self::PARSE_NAMESPACE);
        $noun  = !empty($noun)  ? ".".$noun  : $noun;
        return mb_ucfirst($this->trans(mb_strtolower($class.$noun), [], self::DOMAIN_ENTITY));
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