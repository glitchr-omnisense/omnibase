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

final class BaseTwigExtension extends AbstractExtension
{
    protected string $projectDir;
    public function __construct(TranslatorInterface $translator) {

        BaseController::$foundBaseTwigExtension = true;

        $this->translator = $translator;
        $this->intlExtension = new IntlExtension();

        $this->projectDir = dirname(__FILE__, 6); // Might be computed in a way that doesn't rely on file position..
    }

    public function getFilters()
    {
        return [
            new TwigFilter('time',        [$this,'time']),
            new TwigFilter('url',         [$this, 'url']),
            new TwigFilter('trans2',      [$this, 'trans2']),
            new TwigFilter('highlight',   [$this,'highlight']),
            new TwigFilter('image',       [$this,'image'], ['needs_environment' => true, 'needs_context' => true]),
            new TwigFilter('datetime',    [$this,'datetime'], ['needs_environment' => true]),
            new TwigFilter('lessThan',    [$this,'lessThan'], ['needs_environment' => true]),
            new TwigFilter('greaterThan', [$this,'greaterThan'], ['needs_environment' => true])
        ];
    }

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

    public function lessThan(Environment $env, $date, $diff): string
    {
        if(is_string($date)) $date = new \DateTime($date);
        if($date instanceof \DateTime) $date = $date->getTimestamp();
        if(is_string($diff)) $diff = new \DateTime($diff);
        if($diff instanceof \DateTime) $diff = $diff->getTimestamp() - time();

        $deltaTime = time() - $date;
        return $deltaTime < $diff;
    }

    public function greaterThan(Environment $env, $date, int $diff): string
    {
        if(is_string($date)) $date = new \DateTime($date);
        if($date instanceof \DateTime) $date = $date->getTimestamp();
        if(is_string($diff)) $diff = new \DateTime($diff);
        if($diff instanceof \DateTime) $diff = $diff->getTimestamp() - time();

        $deltaTime = time() - $date;
        return $deltaTime > $diff;
    }

    public function truncate($string, $maxLength = 30, $replacement = '', $truncAtSpace = false)
    {
        $maxLength -= strlen($replacement);
        $length = strlen($string);

        if($length <= $maxLength)
            return $string;

        if( $truncAtSpace && ($space_position = strrpos($string, ' ', $maxLength-$length)) )
            $maxLength = $space_position;

        return substr_replace($string, $replacement, $maxLength)."..";
    }

    public function url(string $url)
    {
        $url = trim($url);
        
        $parseUrl = parse_url($url);
        if(!array_key_exists("schema", $parseUrl)) {
            
            if(str_starts_with($url, "http://") || str_starts_with($url, "https://")) $domain = "";
            else $domain = ($_SERVER['HTTPS'] ? "https://" : "http://") . $_SERVER['SERVER_NAME'];

            if (!empty($domain)) $joint = (str_starts_with($url, "/")) ? "" : "/";
            else $joint = "";
            
            $url = $domain . $joint . $url;
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

    public function trans2(?string $id, array $parameters = array(), ?string $domain = null, ?string $locale = null)
    {
        if($id === null)
            throw new Exception("trans2() called, but translation ID is empty..");

        // Default locale translator
        $defaultLocale = "en";
        $locale = strtolower(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? $locale ?? $defaultLocale, 0, 2));
        setLocale(LC_ALL, $locale); // might be used by some default functions

        // Default domain fallback
        $domain = $domain ?? null;
        if (preg_match("/^\{*[ ]*[a-zA-Z0-9_.]+[.]{1}[a-zA-Z0-9_]+[ ]*\}*$/", $id)) {

            $array  = explode(".", $id);
            if ($domain == null) {
                $domain = array_shift($array);
                $id     = implode(".", $array);
            }

        } else {

            // Check if dot structure
            return preg_replace_callback(
                "/\{[ ]*[a-zA-Z0-9_.]+[.]{1}[a-zA-Z0-9_]+[ ]*\}/",
                function ($key) use ($id, $parameters, $domain, $locale) {
                    return $this->trans2($id, $parameters, $domain, $locale);
                }, $id);
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

        if ($trans == $id && preg_match("/^\{*[ ]*[a-zA-Z0-9_.]+[.]{1}[a-zA-Z0-9_]+[ ]*\}*$/", $id))
            return $domain.'.'.$id;

        return trim($trans);
    }
}
