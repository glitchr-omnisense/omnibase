<?php

namespace Base\Twig;

use Base\Service\BaseService;
use Base\Controller\BaseController;
use Base\Entity\User\Notification;
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
            new TwigFilter('trans2', [$this, 'trans2']),
            new TwigFilter('datetime', [$this,'datetime'], ['needs_environment' => true])
        ];
    }

    public function datetime(Environment $env, $date, string $pattern = "YYYY-MM-dd HH:mm:ss", ?string $dateFormat = 'medium', ?string $timeFormat = 'medium', $timezone = null, string $calendar = 'gregorian', string $locale = null): string
    {
        if(is_string($date)) return $date;
        return $this->intlExtension->formatDateTime($env, $date, 'none', $timeFormat, $pattern, $timezone, $calendar, $locale);
    }

    public function trans2(string $id, array $parameters = array(), ?string $domain = null, ?string $locale = null)
    {
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

            $parameters[($addBrackets) ? "{" . ((string) $key) . "}" : $key] = htmlspecialchars($element);
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
