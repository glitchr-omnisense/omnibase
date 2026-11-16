<?php

namespace Base\Repository\Analytics;

use Base\Entity\Analytics\Visit;
use Base\Database\Repository\ServiceEntityRepository;
use Base\Database\Repository\Finder\Aggregate;
use Base\Database\Repository\Finder\Change;
use Base\Database\Repository\Finder\Criterion;
use Base\Database\Repository\Finder\Group;
use Base\Database\Repository\Finder\Parser;
use Base\Repository\Analytics\Visits;

/**
 * @extends ServiceEntityRepository<Visit>
 */
class VisitRepository extends ServiceEntityRepository implements Visits
{
    /**
     * Insert-if-absent, not insert-or-update: a presence row only ever
     * needs to exist once per (hour, subject) - a second sighting the same
     * hour is a silent no-op, not an update. INSERT IGNORE (rather than a
     * SELECT-then-INSERT round trip) makes that race-safe under concurrent
     * requests from the same subject too.
     *
     * $date is bucketed down to the top of its hour here, same reasoning
     * as PageViewRepository::incrementView()'s own bucketing.
     */
    public function recordPresence(string $subjectType, string $subjectId, \DateTimeImmutable $date): void
    {
        $key = ['date' => self::hour($date), 'subjectType' => $subjectType, 'subjectId' => mb_substr($subjectId, 0, 64)];
        if ($this->count($key) > 0) {
            return;
        }
        try {
            $this->sql('INSERT INTO {Visit} ({Visit.date}, {Visit.subjectType}, {Visit.subjectId}) VALUES (?, ?, ?)', array_values($key));
        } catch (\Throwable $exception) {
            if (0 === $this->count($key)) {
                throw $exception;
            }
        }
    }

    /**
     * True unique count over the window (not an approximation): each
     * subject contributes at most one row per day it was seen, so
     * COUNT(DISTINCT subject_id) across the date range never double-counts
     * a subject who returned on multiple days within it.
     */
    public function countUnique(string $subjectType, ?\DateTimeImmutable $since = null): int
    {
        // COUNT(DISTINCT subjectId) of the type, since the moment given.
        return (int) (null === $since
            ? $this->distinctCountForSubjectIdBySubjectType($subjectType)
            : $this->distinctCountForSubjectIdBySubjectTypeAndDateGreaterEqualTo($subjectType, $since));
    }

    /**
     * Retention: of the subjects seen in [$previousSince, $currentSince), how
     * many were seen again from $currentSince on.
     *
     * Both numbers come from one scan rather than two queries, so they cannot
     * disagree about who "previous" was. A subject first seen in the current
     * window is not in either count - retention is about coming BACK, and a
     * newcomer has nothing to come back to.
     *
     * @return array{previous: int, returning: int}
     */
    public function returning(string $subjectType, \DateTimeImmutable $previousSince, \DateTimeImmutable $currentSince): array
    {
        $utc = new \DateTimeZone('UTC');
        $previous = array_column($this->findValuesBy(['subjectId'], ['subjectType' => $subjectType, Group::of(Parser::SEPARATOR_AND,
            Criterion::of('date', $previousSince->setTimezone($utc), Parser::OPTION_GREATER_EQUAL),
            Criterion::of('date', $currentSince->setTimezone($utc), Parser::OPTION_LOWER),
        )], distinct: true), 'subjectId');
        $current = [] === $previous ? [] : array_column($this->findValuesBy(['subjectId'], ['subjectType' => $subjectType, 'subjectId' => $previous,
            Group::of(Parser::SEPARATOR_AND, Criterion::of('date', $currentSince->setTimezone($utc), Parser::OPTION_GREATER_EQUAL)),
        ], distinct: true), 'subjectId');

        return ['previous' => \count($previous), 'returning' => \count($current)];
    }

    /**
     * One row per calendar day in range - unlike countUnique() (a single
     * total over the whole window), a subject seen on multiple days
     * within range legitimately contributes to EACH of those days here;
     * that's the correct semantics for "how many distinct subjects were
     * active on day X", which is what a trend chart needs.
     *
     * Raw SQL, same reason as PageViewRepository::dailyBreakdown(): folding
     * this table's own hourly rows back down to one row per DAY needs
     * DATE(date), which the finder language does not group by.
     *
     * @return array<string, int> date (Y-m-d) => distinct subject count
     */
    public function dailyBreakdown(string $subjectType, \DateTimeImmutable $since): array
    {
        $breakdown = [];
        foreach ($this->findValuesBy(['bucket' => Aggregate::day('date'), 'count' => Aggregate::countDistinct('subjectId')], ['subjectType' => $subjectType,
            Group::of(Parser::SEPARATOR_AND, Criterion::of('date', $since->setTimezone(new \DateTimeZone('UTC')), Parser::OPTION_GREATER_EQUAL)),
        ], ['bucket' => 'ASC']) as $row) {
            $breakdown[(string) $row['bucket']] = (int) $row['count'];
        }

        return $breakdown;
    }

    /**
     * One row per HOUR in range - the hourly counterpart to
     * dailyBreakdown(), for the one range ("today") an hour-by-hour curve
     * is actually legible for. No zero-fill here, same division of
     * responsibility as PageViewRepository::hourlyBreakdown() vs
     * Analytics::hourlyBreakdown().
     *
     * @return array<string, int> hour ("Y-m-d H:i:s") => distinct subject count
     */
    public function hourlyBreakdown(string $subjectType, \DateTimeImmutable $since): array
    {
        $breakdown = [];
        foreach ($this->findValuesBy(['bucket' => 'date', 'count' => Aggregate::countDistinct('subjectId')], ['subjectType' => $subjectType,
            Group::of(Parser::SEPARATOR_AND, Criterion::of('date', $since->setTimezone(new \DateTimeZone('UTC')), Parser::OPTION_GREATER_EQUAL)),
        ], ['bucket' => 'ASC']) as $row) {
            $breakdown[(string) $row['bucket']] = (int) $row['count'];
        }

        return $breakdown;
    }

    private static function hour(\DateTimeImmutable $date): \DateTimeImmutable
    {
        $date = $date->setTimezone(new \DateTimeZone('UTC'));

        return $date->setTime((int) $date->format('H'), 0, 0);
    }
}
