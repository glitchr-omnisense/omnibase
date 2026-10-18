<?php

namespace Base\Service\Model;

use Omnibridge\Twig\Contract\Translations;

/**
 * A number of seconds in words - "2 jours 3 heures 5 minutes" - from the
 * application's texts (base.years, base.months, base.days, base.hours,
 * base.minutes, base.seconds: ICU plurals on {0}), as Base\Service\Translator::
 * transTime() said it: the time left before a token expires, before a thread
 * is published, an order abandoned (plan "omnibase agnostique", C4b).
 *
 * Asked through the Translations contract, so the texts are the ones the
 * pages print: under Symfony omnibase's translator chain, the texts rewritten
 * in the back office first. A month is 30 days, a year 12 months. Nothing for
 * no time left (zero or less).
 */
final class Duration
{
    public static function spell(int $seconds, Translations $translations): string
    {
        if ($seconds <= 0) {
            return "";
        }

        $time = $seconds;
        $seconds = fmod($time, 60);
        $time = intdiv($time, 60);
        $minutes = fmod($time, 60);
        $time = intdiv($time, 60);
        $hours = fmod($time, 24);
        $time = intdiv($time, 24);
        $days = fmod($time, 30);
        $time = intdiv($time, 30);
        $months = fmod($time, 12);
        $years = intdiv($time, 12);

        $str =
            ($years ?: "") . " " . $translations->trans("base.years", [$years]) . " " .
            ($months ?: "") . " " . $translations->trans("base.months", [$months]) . " " .
            ($days ?: "") . " " . $translations->trans("base.days", [$days]) . " " .
            ($hours ?: "") . " " . $translations->trans("base.hours", [$hours]) . " " .
            ($minutes ?: "") . " " . $translations->trans("base.minutes", [$minutes]) . " " .
            ($seconds ?: "") . " " . $translations->trans("base.seconds", [$seconds]);

        return trim($str);
    }
}
