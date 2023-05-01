<?php

namespace Base\Service\Model;

use DateTime;
use DateTimeZone;
use IntlCalendar;
use IntlDateFormatter;
use IntlTimeZone;

class IntlDateTime extends DateTime
{
    /**
     * @var IntlDateFormatter
     */
    protected IntlDateFormatter $intlDateFormatter;

    public function __construct(
        DateTime|string                       $datetime = "now",
        ?string                               $locale = null,
        int                                   $dateType = IntlDateFormatter::FULL,
        int                                   $timeType = IntlDateFormatter::FULL,
        IntlTimeZone|DateTimeZone|string|null $timezone = null,
        IntlCalendar|int|null                 $calendar = null
    )
    {
        if (is_string($datetime)) {
            parent::__construct($datetime, $timezone);
        } else {
            parent::__construct();
            $this->setTimestamp($datetime->getTimestamp());
            $this->setTimezone($datetime->getTimezone());
        }

        $this->intlDateFormatter = new IntlDateFormatter($locale, $dateType, $timeType, $timezone, $calendar);
    }

    public function format(string $format): string
    {
        $this->intlDateFormatter->setPattern($format);
        return $this->intlDateFormatter->format($this);
    }

    public static function createFromDateTime(
        DateTime                 $datetime,
        ?string                  $locale = null,
        int                      $dateType = IntlDateFormatter::FULL,
        int                      $timeType = IntlDateFormatter::FULL,
        DateTimeZone|string|null $timezone = null,
        int|null                 $calendar = null
    )
    {
        return new IntlDateTime($datetime, $locale, $dateType, $timeType, $timezone, $calendar);
    }
}
