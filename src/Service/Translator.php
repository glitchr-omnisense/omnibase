<?php

namespace Base\Service;

use AsyncAws\Core\Exception\LogicException;
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

    public const STRUCTURE_DOT = "^[@a-zA-Z0-9_.]+[.]{1}[a-zA-Z0-9_]+$";
    public const STRUCTURE_DOTBRACKET = "\{[ ]*[@a-zA-Z0-9_.]+[.]{0,1}[a-zA-Z0-9_]+[ ]*\}";
    public const STRUCTURE_BRACKETLIST = ['{}', "[]", "%%"];

    public const TRANSLATION_PROPERTIES   = "_properties";

    public const TRANSLATION_NOUN  = "noun";
    public const NOUN_SINGULAR     = "_singular";
    public const NOUN_PLURAL       = "_plural";

    public const TRANSLATION_GENDERNESS  = "genderness";
    public const GENDERNESS_INCLUSIVE    = "_inclusive";
    public const GENDERNESS_FEMININE     = "_feminine";
    public const GENDERNESS_MASCULINE    = "_masculine";
    public const GENDERNESS_NEUTRAL      = "_neutral";

    public const TRANSLATION_POLITENESS  = "politeness";
    public const POLITENESS_PLAIN        = "_plain";
    public const POLITENESS_POLITE       = "_polite";
    public const POLITENESS_FORMAL       = "_formal";

    /**
     * @var ParameterBag
     */
    protected $parameterBag;

    /**
     * @var Translator
     */
    protected $translator;

    /**
     * @var bool
     */
    protected bool $isDebug;

    public function __construct(\Symfony\Contracts\Translation\TranslatorInterface $translator, KernelInterface $kernel, ParameterBagInterface $parameterBag)
    {
        $this->parameterBag = $parameterBag;
        $this->translator   = $translator;
        $this->isDebug      = $kernel->isDebug();
    }

    public function getLocale(): string
    {
        return $this->translator->getLocale();
    }
    public function setLocale(string $locale)
    {
        $this->translator->setLocale($locale);
        return $this;
    }

    public function getFallbackLocales(): array
    {
        return $this->translator->getFallbackLocales();
    }

    public function transQuiet(TranslatableMessage|string $id, array $parameters = array(), ?string $domain = null, ?string $locale = null, bool $recursive = true, bool $nullable = true): ?string
    {
        return $this->transExists($id, $domain, $locale) ? $this->trans($id, $parameters, $domain, $locale, $recursive) : ($nullable ? null : $id);
    }

    public function trans(TranslatableMessage|string $id, array $parameters = array(), ?string $domain = null, ?string $locale = null, bool $recursive = true): string
    {
        if (!$id) {
            return $id;
        }

        $domainFallback = null;
        if ($id instanceof TranslatableMessage) {
            $domainFallback = $domain;
            $domain = $id->getDomain();
            $parameters = array_merge($id->getParameters(), $parameters);
            $id = $id->getMessage();
        }

        $id = trim($id);
        $customId  = preg_match("/".self::STRUCTURE_DOT."|".self::STRUCTURE_DOTBRACKET."/", $id);
        $startsWithDomainTag   = str_starts_with($id, "@");

        $domain         = $domain         && str_starts_with($domain, "@") ? substr($domain, 1) : ($domain ?? null);
        $domainFallback = $domainFallback && str_starts_with($domainFallback, "@") ? substr($domainFallback, 1) : ($domainFallback ?? null);
        if ($id && $customId) {
            $array  = explode(".", $id);
            if ($startsWithDomainTag) {
                $domain = substr(array_shift($array), 1);
                $id     = implode(".", $array);
            }
        } elseif ($recursive) { // Check if recursive dot structure
            $count = 0;
            $fn = fn ($k) => $this->trans($k, $parameters, $domain, $locale, false);

            $ret = preg_replace_callback("/".self::STRUCTURE_DOT."|".self::STRUCTURE_DOTBRACKET."/", $fn, $id, -1, $count);
            if ($ret != $id) {
                return $ret;
            }

            $ret = $this->translator->trans($ret, $parameters, $domain, $locale);
            if (preg_match("/^{[a-zA-Z0-9]*}$/", $ret)) {
                $ret = $this->translator->trans($ret, $parameters, $domainFallback, $locale);
                if (preg_match("/^{[a-zA-Z0-9]*}$/", $ret)) {
                    return $id;
                }
            }

            return $ret;
        }


        // Replace parameter between brackets
        $bracketList = self::STRUCTURE_BRACKETLIST;
        foreach ($parameters as $key => $element) {
            $brackets = -1;
            if (is_numeric($key)) {
                $brackets = $bracketList[0];
            } elseif (is_string($key)) {
                $pos = array_search($key[0].$key[strlen($key) - 1], $bracketList);
                if ($pos !== false) {
                    continue;
                } // already formatted
            }

            if (preg_match("/^[a-zA-Z0-9_.]+$/", $key) && $brackets < 0) {
                $brackets = begin($bracketList);
            }

            if ($brackets < 0) {
                continue;
            }
            $leftBracket  = $brackets[0];
            $rightBracket = $brackets[1];

            $parameters[$leftBracket.trim($key, $leftBracket.$rightBracket." ").$rightBracket] = $element;

            unset($parameters[$key]);
        }

        // Call for translation with parameter bag variables
        $trans  = $this->translator->trans($id, $parameters, $domain, $locale);
        if (preg_match_all("/%([^%]*)%/", $trans, $matches)) {
            foreach ($matches[1] ?? [] as $key) {
                if (($parameter = $this->parameterBag->get($key))) {
                    $parameters["%".$key."%"] = $parameter;
                }
            }
        }

        // Lookup for nested translations
        while ($this->transExists($trans, $domain, $locale) && $recursive) {
            $trans = $this->trans($trans, $parameters, $domain, $locale, false);
        }

        if ($trans == $id) {
            if ($domainFallback !== false) {
                while ($this->transExists($trans, $domainFallback, $locale) && $recursive) {
                    $trans = $this->trans($trans, $parameters, $domainFallback, $locale, false);
                }
            }

            // Fallback in production
            if ($locale != Localizer::getDefaultLocale() && !$this->isDebug) {
                if ($trans == $id) {
                    $trans = $this->transQuiet($id, $parameters, $domain, Localizer::getDefaultLocale());
                }
                if ($trans == $id && $domainFallback !== false) {
                    $trans = $this->transQuiet($id, $parameters, $domainFallback, Localizer::getDefaultLocale());
                }
            }
        }

        if ($trans == $id && $customId) {
            $trans = $domain && $startsWithDomainTag ? "@".$domain.".".$id : $id;
        }

        return trim($trans ?? "");
    }

    public function parseClass($class, string $parseBy = self::PARSE_NAMESPACE): string
    {
        $class = is_object($class) ? get_class($class) : $class;
        switch($parseBy) {
            case self::PARSE_EXTENDS:

                $parent = class_exists($class) ? get_parent_class($class) : null;

                $class = class_basename($class);
                if ($parent) {
                    $class .= ".".class_basename($parent);
                }
                while (class_exists($parent) && ($parent = get_parent_class($parent))) {
                    $class .= ".".class_basename($parent);
                }

                return camel2snake($class);

            default:
            case self::PARSE_NAMESPACE:

                $class = str_replace(["Proxies\\__CG__\\", "App\\Entity\\", "Base\\Entity\\"], ["", "", "",], $class);
                return camel2snake(implode(".", array_unique(explode("\\", $class))));
        }
    }

    public function transExists(TranslatableMessage|string $id, ?string $domain = null, ?string $locale = null, bool $localeCountry = true): bool
    {
        $locale  ??= $this->getLocale();
        $catalogue = $this->translator->getCatalogue($localeCountry ? Localizer::__toLocale($locale, "_") : Localizer::__toLocaleLang($locale));
        if ($id instanceof TranslatableMessage) {
            $domain ??= $id->getDomain();
            $id       = $id->getMessage();
        }

        $id = trim($id);
        $array  = explode(".", $id);
        if (str_starts_with($id, "@")) {
            $domain = substr(array_shift($array), 1);
            $id     = implode(".", $array);
        }

        $domain = $domain && str_starts_with($domain, "@") ? substr($domain, 1) : ($domain ?? null);
        return $catalogue->has($id, $domain ?? self::DOMAIN_DEFAULT);
    }

    protected function parsePath(string $path)
    {
        $entries = array_starts_with(explode(".", $path), "_");
        return get_permutations(tail($entries, 3));
    }

    protected function transPerms(string $id, array|string $options = [], ?array $parameters = [], ?string $domain = null, ?string $locale = null)
    {
        if (!is_array($options)) {
            $options = array_filter([$options]);
        }

        $politeness = null;
        if (in_array(self::POLITENESS_PLAIN, $options)) {
            $politeness = self::POLITENESS_PLAIN;
        } elseif (in_array(self::POLITENESS_POLITE, $options)) {
            $politeness = self::POLITENESS_POLITE;
        } elseif (in_array(self::POLITENESS_FORMAL, $options)) {
            $politeness = self::POLITENESS_FORMAL;
        }
        $politeness = $politeness ? ".".$politeness : "";

        $genderness = null;
        if (in_array(self::GENDERNESS_INCLUSIVE, $options)) {
            $genderness = self::GENDERNESS_INCLUSIVE;
        } elseif (in_array(self::GENDERNESS_MASCULINE, $options)) {
            $genderness = self::GENDERNESS_MASCULINE;
        } elseif (in_array(self::GENDERNESS_FEMININE, $options)) {
            $genderness = self::GENDERNESS_FEMININE;
        } elseif (in_array(self::GENDERNESS_NEUTRAL, $options)) {
            $genderness = self::GENDERNESS_NEUTRAL;
        }
        $genderness = $genderness ? ".".$genderness : "";

        $noun = null;
        if (in_array(self::NOUN_PLURAL, $options)) {
            $noun = self::NOUN_PLURAL;
        } elseif (in_array(self::NOUN_SINGULAR, $options)) {
            $noun = self::NOUN_SINGULAR;
        }
        $noun = $noun ? ".".$noun : "";

        $in = array_filter([$politeness, $genderness, $noun]);
        $permutations = array_map(fn ($a) => implode("", $a), get_permutations($in, true));
        $permutations[] = "";

        $trans = null;
        foreach ($permutations as $permutation) {
            $trans = $this->transQuiet(mb_strtolower($id.$permutation), $parameters, $domain, $locale);
            if ($trans !== null) {
                break;
            }
        }

        if (!$trans && empty($options)) {
            throw new \LogicException("No translation found for \"@".$domain.".".$id."\" and no permutation option provided");
        }

        return $trans ? mb_ucfirst($trans) : mb_strtolower($id.implode("", $in));
    }

    protected function transPermExists(string $id, array|string $options = [], ?string $domain = null, ?string $locale = null, bool $localeCountry = true)
    {
        if (!is_array($options)) {
            $options = [$options];
        }

        $politeness = null;
        if (in_array(self::POLITENESS_PLAIN, $options)) {
            $politeness = self::POLITENESS_PLAIN;
        } elseif (in_array(self::POLITENESS_POLITE, $options)) {
            $politeness = self::POLITENESS_POLITE;
        } elseif (in_array(self::POLITENESS_FORMAL, $options)) {
            $politeness = self::POLITENESS_FORMAL;
        }
        $politeness = $politeness ? ".".$politeness : "";

        $genderness = null;
        if (in_array(self::GENDERNESS_INCLUSIVE, $options)) {
            $genderness = self::GENDERNESS_INCLUSIVE;
        } elseif (in_array(self::GENDERNESS_MASCULINE, $options)) {
            $genderness = self::GENDERNESS_MASCULINE;
        } elseif (in_array(self::GENDERNESS_FEMININE, $options)) {
            $genderness = self::GENDERNESS_FEMININE;
        } elseif (in_array(self::GENDERNESS_NEUTRAL, $options)) {
            $genderness = self::GENDERNESS_NEUTRAL;
        }
        $genderness = $genderness ? ".".$genderness : "";

        $noun = null;
        if (in_array(self::NOUN_PLURAL, $options)) {
            $noun = self::NOUN_PLURAL;
        } elseif (in_array(self::NOUN_SINGULAR, $options)) {
            $noun = self::NOUN_SINGULAR;
        }
        $noun = $noun ? ".".$noun : "";

        $in = array_filter([$politeness, $genderness, $noun]);
        $permutations = array_map(fn ($a) => implode("", $a), get_permutations($in, true));
        $permutations[] = "";

        $trans = null;
        foreach ($permutations as $permutation) {
            $trans = $this->transQuiet(mb_strtolower($id.$permutation), [], $domain, $locale, $localeCountry);
            if ($trans !== null) {
                return true;
            }
        }

        return false;
    }

    public function transRoute(string $routeName, ?string $domain = null): ?string
    {
        $domain = $domain ? $domain."." : "@controllers.";
        return $this->trans($domain.$routeName.".title");
    }

    public function transRouteExists(string $routeName, ?string $domain = null): bool
    {
        $domain = $domain ? $domain."." : "@controllers.";
        return $this->transExists($domain.$routeName.".title");
    }

    public function transEnum(?string $value, string $class, null|string|array $options = self::NOUN_SINGULAR): ?string
    {
        if (class_exists($class)) {
            $declaringClass = $class;
        } elseif (Type::hasType($class)) {
            $declaringClass = get_class(Type::getType($class));
        } else {
            return $value;
        }

        while ((count(array_filter($declaringClass::getPermittedValues(false), fn ($c) => $c === $value)) == 0)) {
            $declaringClass = get_parent_class($declaringClass);
            if ($declaringClass === Type::class || $declaringClass === null) {
                $declaringClass = $class;
                break;
            }
        }

        $value = $value ? ".".$value : "";
        $offset = is_subclass_of($class, SetType::class) ? -3 : -2;
        $class  = $this->parseClass($declaringClass, self::PARSE_EXTENDS);
        $class  = implode(".", array_slice(explode(".", $class), 0, $offset));

        return $class ? $this->transPerms($class.$value, $options, [], self::DOMAIN_ENUM) : null;
    }

    public function transEnumExists(string $value, string $class, string|array $options = self::NOUN_SINGULAR): bool
    {
        $declaringClass = $class;
        while ((count(array_filter($declaringClass::getPermittedValues(false), fn ($c) => $c === $value)) == 0)) {
            $declaringClass = get_parent_class($declaringClass);
            if ($declaringClass === Type::class || $declaringClass === null) {
                $declaringClass = $class;
                break;
            }
        }

        $value = $value ? ".".$value : "";
        $offset = is_subclass_of($class, SetType::class) ? -3 : -2;
        $class  = $this->parseClass($declaringClass, self::PARSE_EXTENDS);
        $class  = implode(".", array_slice(explode(".", $class), 0, $offset));

        return $class ? $this->transPermExists($class.$value, $options, self::DOMAIN_ENUM) : false;
    }

    public function transEntity(mixed $entityOrClassName, ?string $property = null, string|array $options = self::NOUN_SINGULAR): ?string
    {
        if (!is_array($options)) {
            $options = array_filter([$options]);
        }
        if (is_object($entityOrClassName)) {
            $entityOrClassName = get_class($entityOrClassName);
        }

        $entityOrClassName = $this->parseClass($entityOrClassName, self::PARSE_NAMESPACE);
        $property = $property ? ".".$property : "";

        return $entityOrClassName ? $this->transPerms($entityOrClassName.camel2snake($property), $options, [], self::DOMAIN_ENTITY) : null;
    }

    public function transEntityExists(mixed $entityOrClassName, ?string $property = null, string|array $options = self::NOUN_SINGULAR): bool
    {
        if (!is_array($options)) {
            $options = [$options];
        }
        if (is_object($entityOrClassName)) {
            $entityOrClassName = get_class($entityOrClassName);
        }

        $entityOrClassName = $this->parseClass($entityOrClassName, self::PARSE_NAMESPACE);
        $property = $property ? ".".$property : "";

        return $this->transPermExists($entityOrClassName.camel2snake($property), $options, self::DOMAIN_ENTITY);
    }

    public function transTime(int $time): string
    {
        if ($time > 0) {
            $seconds = fmod($time, 60);
            $time    = intdiv($time, 60);
            $minutes = fmod($time, 60);
            $time    = intdiv($time, 60);
            $hours   = fmod($time, 24);
            $time    = intdiv($time, 24);
            $days    = fmod($time, 30);
            $time    = intdiv($time, 30);
            $months  = fmod($time, 12);
            $years   = intdiv($time, 12);

            $str =
                ($years ? $years : "") . " ". $this->trans("base.years", [$years])  ." ".
                ($months ? $months : "") . " ". $this->trans("base.months", [$months]) ." ".
                ($days ? $days : "") . " ". $this->trans("base.days", [$days])   ." ".
                ($hours ? $hours : "") . " ". $this->trans("base.hours", [$hours])  ." ".
                ($minutes ? $minutes : "") . " ". $this->trans("base.minutes", [$minutes])." ".
                ($seconds ? $seconds : "") . " ". $this->trans("base.seconds", [$seconds]);

            return trim($str);
        }

        return "";
    }
}
