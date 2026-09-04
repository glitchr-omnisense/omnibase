<?php

namespace Base\Entity\Layout\Attribute\Adapter;

use Base\Database\Attribute\DiscriminatorEntry;
use Base\Entity\Layout\Attribute\Adapter\Common\AbstractRuleAdapter;
use Base\Field\Type\DateTimePickerType;
use DateTimeImmutable;
use DateTimeInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\Adapter\DateRangeAdapterRepository;
use Base\Database\Attribute\Cache;

/**
 * "Are we inside this window?" - the coupon predicate.
 *
 * Two shapes, because the two questions people actually ask are different:
 *
 *     {"from": "2026-12-01", "to": "2026-12-25"}
 *         one window, once. A launch offer.
 *
 *     {"from": "12-01", "to": "12-25", "annual": true}
 *         the same window every year. "Valid in December", which is what a
 *         seasonal coupon means and what a single absolute range cannot say
 *         without being rewritten each December.
 *
 * Either end may be omitted: from alone is "from then on", to alone is "until
 * then". Both omitted is always true, which is the right reading of a rule
 * that has been created but not yet filled in - a half-configured condition
 * should not silently deny everybody.
 *
 * The annual form handles a window that crosses new year. "from 12-15 to
 * 01-15" is a real offer, and comparing month-day pairs naively would make it
 * match nothing at all, because 12-15 sorts after 01-15. When the ends are
 * inverted the test is inverted with them: inside means at or after the
 * start, OR at or before the end.
 *
 * The subject is the instant to judge, so a rule can be asked about a date
 * other than now ("would this coupon have been valid on the order date?").
 * Anything that is not a date means now.
 */
#[ORM\Entity(repositoryClass: DateRangeAdapterRepository::class)]
#[Cache(usage: "NONSTRICT_READ_WRITE", associations: "ALL")]
#[DiscriminatorEntry(value: "date_range")]
class DateRangeAdapter extends AbstractRuleAdapter
{
    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-calendar-day"];
    }

    public static function getType(): string
    {
        return DateTimePickerType::class;
    }

    public function getOptions(): array
    {
        return [];
    }

    public function resolve(mixed $value): mixed
    {
        return is_array($value) ? $value : [];
    }

    public function supports(mixed $value): bool
    {
        return is_array($value) && (($value["from"] ?? null) || ($value["to"] ?? null));
    }

    public function compliesWith(mixed $value, mixed $subject): bool
    {
        if (!is_array($value)) {
            return true;
        }

        $at = $subject instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($subject)
            : new DateTimeImmutable("now");

        $from = $value["from"] ?? null;
        $to   = $value["to"] ?? null;

        if (!$from && !$to) {
            return true;
        }

        return ($value["annual"] ?? false)
            ? $this->withinAnnual($at, $from, $to)
            : $this->withinAbsolute($at, $from, $to);
    }

    private function withinAbsolute(DateTimeImmutable $at, ?string $from, ?string $to): bool
    {
        if ($from && $at < new DateTimeImmutable($from)) {
            return false;
        }

        if ($to && $at > new DateTimeImmutable($to)) {
            return false;
        }

        return true;
    }

    /**
     * Compares month-day only, so the window recurs every year.
     */
    private function withinAnnual(DateTimeImmutable $at, ?string $from, ?string $to): bool
    {
        $today = $at->format("m-d");
        $start = $from ? $this->monthDay($from) : null;
        $end   = $to ? $this->monthDay($to) : null;

        if ($start && $end) {
            // A window that crosses new year has its ends inverted, and so
            // does the test - see the class comment.
            return $start <= $end
                ? ($today >= $start && $today <= $end)
                : ($today >= $start || $today <= $end);
        }

        if ($start) {
            return $today >= $start;
        }

        return $today <= $end;
    }

    /** Accepts "12-01" as well as a full date, so either may be stored. */
    private function monthDay(string $value): string
    {
        if (1 === preg_match('/^\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        return (new DateTimeImmutable($value))->format("m-d");
    }
}
