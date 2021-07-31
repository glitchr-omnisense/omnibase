<?php

namespace Base\Twig;

use Base\Service\BaseService;
use Base\Controller\BaseController;
use Base\Entity\User\Notification;
use Exception;
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
    public function __construct(TranslatorInterface $translator) {

        BaseController::$foundBaseTwigExtension = true;

        $this->translator = $translator;
        $this->intlExtension = new IntlExtension();
    }

    public function getFilters()
    {
        return [
            new TwigFilter('url',         [$this, 'url']),
            new TwigFilter('trans2',      [$this, 'trans2']),
            new TwigFilter('datetime',    [$this,'datetime'], ['needs_environment' => true]),
            new TwigFilter('lessThan',    [$this,'lessThan'], ['needs_environment' => true]),
            new TwigFilter('greaterThan', [$this,'greaterThan'], ['needs_environment' => true])
        ];
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
        dump($deltaTime, $diff, $deltaTime < $diff);
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

    function truncate($string, $maxLength = 30, $replacement = '', $truncAtSpace = false)
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
        $parseUrl = parse_url($url);
        if(!array_key_exists("schema", $parseUrl))
            $url = ($_SERVER['HTTPS'] ? "https://" : "http://") . $_SERVER['SERVER_NAME'] . (str_starts_with($url, "/") ? "" : "/") . $url;
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

        return $trans;
    }
}
