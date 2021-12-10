<?php

namespace Base\Service;

class Translator extends \Symfony\Bundle\FrameworkBundle\Translation\Translator implements TranslatorInterface
{
    public const STRUCTURE_DOT = "^[@a-zA-Z0-9_.]+[.]{0,1}[a-zA-Z0-9_]+$";
    public const STRUCTURE_DOTBRACKET = "\{[ ]*[@a-zA-Z0-9_.]+[.]{0,1}[a-zA-Z0-9_]+[ ]*\}";
    public function trans(?string $id, array $parameters = array(), ?string $domain = null, ?string $locale = null, bool $recursive = true)
    {
        if($id === null) return null;

        $id = trim($id);
        $domain = str_starts_with($domain, "@") ? substr($domain, 1) : $domain ?? null;

        if ($id && preg_match("/".self::STRUCTURE_DOT."|".self::STRUCTURE_DOTBRACKET."/", $id)) {

            $array  = explode(".", $id);
            if(str_starts_with($id, "@")) {
                $domain = substr(array_shift($array), 1);
                $id     = implode(".", $array);
            }

        } else if($recursive) { // Check if recursive dot structure

            $count = 0;
            $fn = function ($key) use ($id, $parameters, $domain, $locale) { return $this->trans($id, $parameters, $domain, $locale, false); };
            $ret = preg_replace_callback("/".self::STRUCTURE_DOT."|".self::STRUCTURE_DOTBRACKET."/", $fn, $id, -1, $count);

            return ($ret == $id ? parent::trans($ret, $parameters, $domain, $locale) : $ret);
        }

        // Replace parameter between brackets
        foreach ($parameters as $key => $element) {

            $addBrackets  = is_string($key) && ($key[0] != '{' || $key[strlen($key) - 1] != '}');
            $addBrackets |= is_numeric($key);

            $parameters[($addBrackets) ? "{" . ((string) $key) . "}" : $key] = $element; //htmlspecialchars($element);
            if ($addBrackets) unset($parameters[$key]);
        }
        
        // Call for translation with custom parameters    
        $trans = parent::trans($id, $parameters, $domain, $locale);
        if ($trans == $id && preg_match("/".self::STRUCTURE_DOT."|".self::STRUCTURE_DOTBRACKET."/", $id))
            return ($domain ? "@".$domain.".".$id : $id);
        
        return trim($trans);
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