<?php

namespace Base\View;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A moment as a pattern says it (plan "omnibase agnostic", step F4): the logic of the |datetime
 * filter, formatted by PHP's intl as Twig's format_datetime() formats it (twig/intl-extra), without
 * Twig - FunctionTwigExtension calls it with Twig's time zone, Blade and a React page's props with
 * the application's.
 */
final class Dates
{
    private const TIME_FORMATS = [
        'none' => \IntlDateFormatter::NONE,
        'short' => \IntlDateFormatter::SHORT,
        'medium' => \IntlDateFormatter::MEDIUM,
        'long' => \IntlDateFormatter::LONG,
        'full' => \IntlDateFormatter::FULL,
    ];

    /** @var array<string, \IntlDateFormatter> */
    private array $formatters = [];

    /**
     * @param TranslatorInterface|null $translator the page's locale, when none is given
     * @param \DateTimeZone|string|null $timezone the zone a moment is printed in, when none is given (PHP's default without it)
     */
    public function __construct(
        private readonly ?TranslatorInterface $translator = null,
        private readonly \DateTimeZone|string|null $timezone = null,
    ) {
    }

    /** Whether a string is a moment in ISO 8601: a date, a time, and a zone or not. */
    public static function isMoment(string $value): bool
    {
        return 1 === preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:Z|[+-]\d{2}:?\d{2})?$/', $value);
    }

    /**
     * A moment as a pattern says it, lowered. A string in ISO 8601 (a screen's moment,
     * Base\Screen\Shape::moment(): 2026-04-10T18:05:00+00:00) is that moment, printed as the
     * DateTime it came from was; any other string is given back lowered (a word: "présent");
     * nothing gives the pattern. Several patterns give a list.
     *
     * @param string|list<string>                $pattern
     * @param \DateTimeZone|string|false|null    $timezone the zone, null for the default, false to keep UTC
     * @param \DateTimeZone|string|null          $default  the default zone of this call (a view engine's), over the helper's
     *
     * @return string|list<string>
     */
    public function datetime(\DateTimeInterface|\DateInterval|int|string|null $datetime, array|string $pattern = 'YYYY-MM-dd HH:mm:ss', ?string $dateFormat = 'medium', ?string $timeFormat = 'medium', \DateTimeZone|string|false|null $timezone = null, string $calendar = 'gregorian', ?string $locale = null, \DateTimeZone|string|null $default = null): array|string
    {
        if (null === $locale) {
            $locale = $this->translator?->getLocale() ?? \Locale::getDefault();
        }
        if (\is_array($pattern)) {
            $array = [];
            foreach ($pattern as $p) {
                $array[] = $this->datetime($datetime, $p, $dateFormat, $timeFormat, $timezone, $calendar, $locale, $default);
            }

            return $array;
        }

        if (null == $datetime) {
            return $pattern;
        }
        if ($datetime instanceof \DateTimeInterface) {
            $datetime = $datetime->getTimestamp();
        }
        if ($datetime instanceof \DateInterval) {
            $datetime = time() + (int) $datetime->format('s');
        }
        if (\is_string($datetime) && self::isMoment($datetime)) {
            $datetime = (new \DateTimeImmutable($datetime))->getTimestamp();
        }
        if (\is_string($datetime)) {
            return mb_strtolower($datetime);
        }

        // As twig/intl-extra's formatDateTime() with no date part (the filter never printed one).
        return mb_strtolower($this->format($datetime, 'none', $timeFormat, $pattern, $timezone, $calendar, $locale, $default));
    }

    /**
     * A timestamp formatted as Twig's format_datetime() formats it: in the zone given (a name or a
     * zone), else the default one; false keeps UTC.
     */
    public function format(int $timestamp, ?string $dateFormat, ?string $timeFormat, string $pattern, \DateTimeZone|string|false|null $timezone, ?string $calendar, string $locale, \DateTimeZone|string|null $default = null): string
    {
        $date = new \DateTime('@'.$timestamp);
        if (false !== $timezone) {
            $zone = $timezone ?? $default ?? $this->timezone ?? date_default_timezone_get();
            $date->setTimezone($zone instanceof \DateTimeZone ? $zone : new \DateTimeZone($zone));
        }
        $formatterTimezone = \is_string($timezone) ? new \DateTimeZone($timezone) : ($timezone ?: $date->getTimezone());

        if (null !== $dateFormat && !isset(self::TIME_FORMATS[$dateFormat])) {
            throw new \InvalidArgumentException(sprintf('The date format "%s" does not exist, known formats are: "%s".', $dateFormat, implode('", "', array_keys(self::TIME_FORMATS))));
        }
        if (null !== $timeFormat && !isset(self::TIME_FORMATS[$timeFormat])) {
            throw new \InvalidArgumentException(sprintf('The time format "%s" does not exist, known formats are: "%s".', $timeFormat, implode('", "', array_keys(self::TIME_FORMATS))));
        }
        $dateType = null === $dateFormat ? \IntlDateFormatter::MEDIUM : self::TIME_FORMATS[$dateFormat];
        $timeType = null === $timeFormat ? \IntlDateFormatter::MEDIUM : self::TIME_FORMATS[$timeFormat];
        $calendarType = null === $calendar || 'gregorian' === $calendar ? \IntlDateFormatter::GREGORIAN : \IntlDateFormatter::TRADITIONAL;

        $hash = $locale.'|'.$dateType.'|'.$timeType.'|'.$formatterTimezone->getName().'|'.$calendarType.'|'.$pattern;
        $formatter = $this->formatters[$hash] ??= new \IntlDateFormatter($locale, $dateType, $timeType, $formatterTimezone, $calendarType, $pattern);

        if (false === $ret = $formatter->format($date)) {
            throw new \RuntimeException('Unable to format the given date.');
        }

        return $ret;
    }
}
