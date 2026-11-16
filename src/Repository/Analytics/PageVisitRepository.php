<?php

namespace Base\Repository\Analytics;

use Base\Database\Repository\ServiceEntityRepository;
use Base\Database\Repository\Finder\Aggregate;
use Base\Database\Repository\Finder\Change;
use Base\Database\Repository\Finder\Criterion;
use Base\Database\Repository\Finder\Group;
use Base\Database\Repository\Finder\Parser;
use Base\Entity\Analytics\PageVisit;
use Base\Repository\Analytics\PageVisits;

/**
 * @extends ServiceEntityRepository<PageVisit>
 */
class PageVisitRepository extends ServiceEntityRepository implements PageVisits
{
    /**
     * Insert-if-absent, bucketed to the hour - same contract as
     * VisitRepository::recordPresence(), with the page added.
     */
    public function recordPresence(string $path, string $subjectType, string $subjectId, \DateTimeImmutable $date): void
    {
        $key = ['date' => self::hour($date), 'path' => mb_substr($path, 0, 255), 'subjectType' => $subjectType, 'subjectId' => mb_substr($subjectId, 0, 64)];
        if ($this->count($key) > 0) {
            return;
        }
        try {
            $this->sql('INSERT INTO {PageVisit} ({PageVisit.date}, {PageVisit.path}, {PageVisit.subjectType}, {PageVisit.subjectId}) VALUES (?, ?, ?, ?)', array_values($key));
        } catch (\Throwable $exception) {
            // Written by another request in between: present already.
            if (0 === $this->count($key)) {
                throw $exception;
            }
        }
    }

    /**
     * Unique views over the window: distinct (page, subject) pairs. A reader
     * reloading one page is one unique view; the same reader on two pages is
     * two. Rows are hourly, so this counts DISTINCT pairs, never rows - over
     * a DISTINCT subquery, since COUNT(DISTINCT a, b, c) is MySQL's alone.
     *
     * @param string|string[]|null $path
     */
    public function countUnique(?\DateTimeImmutable $since = null, string|array|null $path = null): int
    {
        // Distinct (page, subject) pairs: a reader reloading one page is one unique view.
        return \count($this->findValuesBy(['path', 'subjectType', 'subjectId'], self::window($since, $path), distinct: true));
    }

    /**
     * One entry per calendar day: distinct (page, subject) pairs seen that
     * day. A reader coming back to the same page on another day counts again
     * on that day - the right answer for a trend line, as with
     * VisitRepository::dailyBreakdown().
     *
     * @param string|string[]|null $path
     *
     * @return array<string, int> date (Y-m-d) => unique views
     */
    public function dailyBreakdown(\DateTimeImmutable $since, string|array|null $path = null): array
    {
        // Distinct (page, subject) pairs per bucket.
        $breakdown = [];
        foreach ($this->findValuesBy(['bucket' => Aggregate::day('date'), 'path', 'subjectType', 'subjectId'], self::window($since, $path), ['bucket' => 'ASC'], distinct: true) as $row) {
            $breakdown[(string) $row['bucket']] = ($breakdown[(string) $row['bucket']] ?? 0) + 1;
        }

        return $breakdown;
    }

    /**
     * @param string|string[]|null $path
     *
     * @return array<string, int> hour ("Y-m-d H:i:s") => unique views
     */
    public function hourlyBreakdown(\DateTimeImmutable $since, string|array|null $path = null): array
    {
        // Distinct (page, subject) pairs per bucket.
        $breakdown = [];
        foreach ($this->findValuesBy(['bucket' => 'date', 'path', 'subjectType', 'subjectId'], self::window($since, $path), ['bucket' => 'ASC'], distinct: true) as $row) {
            $breakdown[(string) $row['bucket']] = ($breakdown[(string) $row['bucket']] ?? 0) + 1;
        }

        return $breakdown;
    }

    private static function hour(\DateTimeImmutable $date): \DateTimeImmutable
    {
        $date = $date->setTimezone(new \DateTimeZone('UTC'));

        return $date->setTime((int) $date->format('H'), 0, 0);
    }

    /**
     * @param string|list<string>|null $path
     *
     * @return array<int|string, mixed>
     */
    private static function window(?\DateTimeImmutable $since, string|array|null $path): array
    {
        $window = null === $since ? [] : [Group::of(Parser::SEPARATOR_AND, Criterion::of('date', $since->setTimezone(new \DateTimeZone('UTC')), Parser::OPTION_GREATER_EQUAL))];

        return null === $path ? $window : [...$window, 'path' => $path];
    }
}
