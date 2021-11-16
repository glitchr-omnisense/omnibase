<?php

namespace Base\Twig;

use Base\Service\BaseService;
use Base\Controller\BaseController;
use Base\Entity\User\Notification;
use Exception;
use Symfony\Bridge\Twig\Mime\WrappedTemplatedEmail;
use Symfony\Contracts\Translation\TranslatorInterface;

use Twig\Environment;
use Twig\TwigFunction;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * @author Marco Meyer <marco.meyerconde@gmail.com>
 *
 */

use Twig\Extra\Intl\IntlExtension;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class BaseTwigExtension extends AbstractExtension
{
    protected string $projectDir;
    public function __construct(TranslatorInterface $translator, ?BaseService $baseService = null) {

        BaseController::$foundBaseTwigExtension = true;

        $this->baseService = $baseService;
        $this->translator = $translator;
        $this->intlExtension = new IntlExtension();

        if($this->baseService)
            $this->projectDir = $this->baseService->getProjectDir();
    }

    public function getFilters()
    {
        return [
            new TwigFilter('time',          [$this, 'time']),
            new TwigFilter('url',           [$this, 'url']),
            new TwigFilter('stringify',     [$this, 'stringify']),
            new TwigFilter('trans2',        [$this, 'trans2']),
            new TwigFilter('highlight',     [$this, 'highlight']),
            new TwigFilter('flatten_array', [$this, 'flattenArray']),
            new TwigFilter('filesize',      [$this, 'filesize']),
            new TwigFilter('lang',          [$this, 'lang']),
            new TwigFilter('country',       [$this, 'country']),
            new TwigFilter('image',         [$this, 'image'],       ['needs_environment' => true, 'needs_context' => true]),
            new TwigFilter('datetime',      [$this, 'datetime'],    ['needs_environment' => true]),
            new TwigFilter('lessThan',      [$this, 'lessThan'],    ['needs_environment' => true]),
            new TwigFilter('greaterThan',   [$this, 'greaterThan'], ['needs_environment' => true])
        ];
    }

    public function filesize($bytes): string
    {
        $size = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $factor = (int) floor(log($bytes) / log(1024));

        return (int) ($bytes / (1024 ** $factor)).@$size[$factor];
    }

    public function flattenArray($array): ?array
    {
        return BaseService::array_flatten($array);
    }

    public function lang(string $locale):     string { return substr($locale, 0, 2); }
    public function country(string $locale):  string { return substr($locale, 3, 2); }
    public function time(int $remainingTime): string
    {
        if($remainingTime > 0) {
        
            $seconds       = fmod  ($remainingTime, 60);
            $remainingTime = intdiv($remainingTime, 60);
            $minutes       = fmod  ($remainingTime, 60);
            $remainingTime = intdiv($remainingTime, 60);
            $hours         = fmod  ($remainingTime, 24);
            $remainingTime = intdiv($remainingTime, 24);
            $days          = fmod  ($remainingTime, 30);
            $remainingTime = intdiv($remainingTime, 30);
            $months        = fmod  ($remainingTime, 12);
            $years         = intdiv($remainingTime, 12);

            return trim($this->trans2("messages.base.years",   [$years])  ." ".
                        $this->trans2("messages.base.months",  [$months]) ." ".
                        $this->trans2("messages.base.days",    [$days])   ." ".
                        $this->trans2("messages.base.hours",   [$hours])  ." ".
                        $this->trans2("messages.base.minutes", [$minutes])." ".
                        $this->trans2("messages.base.seconds", [$seconds]));
        }

        return "";
    }

    public function image(Environment $env, array $context, $image, WrappedTemplatedEmail $email = null): string
    {
        $isEmail = array_key_exists("email", $context) && ($context["email"] instanceof WrappedTemplatedEmail);
        if($isEmail) {

            $email = $context["email"];
            $path = $email->image($image);

        } else {

            $path = $env->getLoader()->getSourceContext($image)->getPath();
            if (substr($path, 0, strlen($this->projectDir)) == $this->projectDir)
                $path = substr($path, strlen($this->projectDir));
        }

        return $path;
    }

    public function datetime(Environment $env, $date, string $pattern = "YYYY-MM-dd HH:mm:ss", ?string $dateFormat = 'medium', ?string $timeFormat = 'medium', $timezone = null, string $calendar = 'gregorian', string $locale = null): string
    {
        if(is_string($date)) return $date;
        return $this->intlExtension->formatDateTime($env, $date, 'none', $timeFormat, $pattern, $timezone, $calendar, $locale);
    }

    public function lessThan(Environment $env, $date, $diff): bool
    {
        if(is_string($date)) $date = new \DateTime($date);
        if($date instanceof \DateTime) $date = $date->getTimestamp();
        if(is_string($diff)) $diff = new \DateTime($diff);
        if($diff instanceof \DateTime) $diff = $diff->getTimestamp() - time();
      
        $deltaTime = time() - $date;

        // dump($deltaTime . "<". $diff . " => ". ($deltaTime < $diff));

        return $deltaTime < $diff;
    }

    public function greaterThan(Environment $env, $date, int $diff): bool
    {
        if(is_string($date)) $date = new \DateTime($date);
        if($date instanceof \DateTime) $date = $date->getTimestamp();
        if(is_string($diff)) $diff = new \DateTime($diff);
        if($diff instanceof \DateTime) $diff = $diff->getTimestamp() - time();

        $deltaTime = time() - $date;
        return $deltaTime > $diff;
    }

    public function truncate($string, $maxLength = 30, $replacement = '', $truncAtSpace = false): string
    {
        $maxLength -= strlen($replacement);
        $length = strlen($string);

        if($length <= $maxLength)
            return $string;

        if( $truncAtSpace && ($space_position = strrpos($string, ' ', $maxLength-$length)) )
            $maxLength = $space_position;

        return substr_replace($string, $replacement, $maxLength)."..";
    }

    public function url(string $url): string
    {
        $url = trim($url);

        $parseUrl = parse_url($url);
        if(!array_key_exists("schema", $parseUrl)) {

            $https = $_SERVER['HTTPS'] ?? ($this->baseService->getSettings("base.settings.use_https") ? $this->baseService->getSettings("base.settings.use_https") : true);
            $serverName = $_SERVER['SERVER_NAME'] ?? ($this->baseService->getSettings("base.settings.domain") ? $this->baseService->getSettings("base.settings.domain") : "localhost");

            if(str_starts_with($url, "http://") || str_starts_with($url, "https://")) $domain = "";
            else $domain = ($https ? "https://" : "http://") . $serverName;

            if (!empty($domain)) $join = (str_starts_with($url, "/")) ? "" : "/";
            else $join = "";

            $url = $domain . $join . $url;
        }

        return $url;
    }

    public function highlight(?string $content, $pattern, $gate = 5)
    {
        // Empty entry
        if ($content == null) return null;
        if ($pattern == null) return null;

        $highlightContent = "";
        if( $gate < 0 ) {

            $highlightContent = preg_replace_callback(
                '/([^ ]*)(' . $pattern . ')([^ ]*)/im',
                function($matches) {

                    if(!isset($matches[2]) || empty($matches[2]))
                        return $matches[0];

                    return '<span class="highlightWord">'.
                                $matches[1].
                                '<span class="highlightPattern">'.$matches[2].'</span>'.
                                $matches[3].
                            '</span>';

                }, $content);

        } else if( preg_match_all('/((?:[^ ]+ ){0,' . $gate . '})([^ ]*)(' . $pattern . ')([^ ]*)((?: [^ ]+){0,' . $gate . '})/im',$content,$matches) ) {

            $priorPatternGate = $matches[1][0] ?? "";
            $priorPattern     = $matches[2][0] ?? "";
            $pattern          = $matches[3][0] ?? ""; //(Case insensitive)
            $afterPattern     = $matches[4][0] ?? "";
            $afterPatternGate = $matches[5][0] ?? "";

            $sentence = $priorPatternGate . $priorPattern . $pattern . $afterPattern . $afterPatternGate;

            if( !str_starts_with($content, $sentence) )
                $highlightContent .= "[..] ";

            $highlightContent .= "<span class='highlightGate'>";
            $highlightContent .= $priorPatternGate;
            $highlightContent .= "<span class='highlightWord'>";
            $highlightContent .= $priorPattern;
            $highlightContent .= "<span class='highlightPattern'>";
            $highlightContent .= $pattern;
            $highlightContent .= "</span>";
            $highlightContent .= $afterPattern;
            $highlightContent .= "</span>";
            $highlightContent .= $afterPatternGate;
            $highlightContent .= "</span>";

            if( !str_ends_with($content, $sentence) )
                $highlightContent .= " [..]";

        }

        return ( empty($highlightContent) ? null : $highlightContent );
    }

    public const DOT_STRUCTURE = "\{*[ ]*[a-zA-Z0-9_.]+[.]{1}[a-zA-Z0-9_]+[ ]*\}*";
    public function trans2(?string $id, array $parameters = array(), ?string $domain = null, ?string $locale = null, $recursive = true)
    {
        if($id === null) return null;

        $domain = $domain ?? null; // Default domain fallback
        if (preg_match("/^".self::DOT_STRUCTURE."$/", $id)) {

            $array  = explode(".", $id);
            if ($domain == null) {
                $domain = array_shift($array);
                $id     = implode(".", $array);
            }

        } else if($recursive) { // Check if recursive dot structure

            $count = 0;
            $fn = function ($key) use ($id, $parameters, $domain, $locale) { return $this->trans2($id, $parameters, $domain, $locale, false); };
            $ret = preg_replace_callback("/^".self::DOT_STRUCTURE."/", $fn, $id, -1, $count);

            return $ret; // If no replacement go to default fallback
        }

        // Replace parameter between brackets
        foreach ($parameters as $key => $element) {

            $addBrackets  = is_string($key) && ($key[0] != '{' || $key[strlen($key) - 1] != '}');
            $addBrackets |= is_numeric($key);

            $parameters[($addBrackets) ? "{" . ((string) $key) . "}" : $key] = $element; //htmlspecialchars($element);
            if ($addBrackets) unset($parameters[$key]);
        }
        
        // Call for translation with custom parameters        
        $domain = $domain ?? "messages";
        $trans = $this->translator->trans($id, $parameters, $domain, $locale);
        if ($trans == $id && preg_match("/^".self::DOT_STRUCTURE."$/", $id))
            return $domain.'.'.$id;

        return trim($trans);
    }


    public function stringify($value): string
    {
        if (null === $value) {
            return '';
        }

        if (\is_string($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (\is_array($value)) {
            return sprintf('Array (%d items)', \count($value));
        }

        if (\is_object($value)) {
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }

            if (method_exists($value, 'getId')) {
                return sprintf('%s #%s', \get_class($value), $value->getId());
            }

            return sprintf('%s #%s', \get_class($value), substr(md5(spl_object_hash($value)), 0, 7));
        }

        return '';
    }
}
