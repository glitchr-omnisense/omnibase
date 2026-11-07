<?php

namespace Base\Repository\Analytics;

use Base\Database\Repository\BackendOnly;
use Base\Database\Repository\Entities;
use Base\Entity\Analytics\PageView;

/**
 * The pages' views, by day and hour (audit "lesringostars face au master plan", step R28; see
 * Base\Database\Repository\Entities): PageViewRepository answers it with the queries
 * its callers ran by name before.
 *
 * @extends Entities<object> of PageView (its repository declares ServiceEntityRepository<PageView>)
 */
interface PageViews extends Entities
{
    #[BackendOnly(['doctrine', 'eloquent'], 'native SQL: an upsert, ON DUPLICATE KEY UPDATE on MySQL, ON CONFLICT elsewhere')]
    public function incrementView(string $path, \DateTimeImmutable $date, string $source = PageView::SOURCE_HUMAN): void;

    public function countViews(?string $path = null, ?\DateTimeImmutable $since = null, ?string $source = null): int;

    public function earliestDate(): ?\DateTimeImmutable;

    /**
     * @param string|list<string>|null $path
     *
     * @return array<mixed>
     */
    #[BackendOnly(['doctrine', 'eloquent'], 'native SQL: SUM() grouped by DATE(), which DQL has no function for')]
    public function dailyBreakdown(\DateTimeImmutable $since, string|array|null $path = null): array;

    /**
     * @param string|list<string>|null $path
     *
     * @return array<mixed>
     */
    #[BackendOnly(['doctrine', 'eloquent'], 'native SQL: SUM() grouped by DATE() and source')]
    public function dailyBreakdownBySource(\DateTimeImmutable $since, string|array|null $path = null): array;

    /**
     * @param string|list<string>|null $path
     *
     * @return array<mixed>
     */
    #[BackendOnly(['doctrine', 'eloquent'], 'native SQL: SUM() grouped by the hour bucket, rows as arrays')]
    public function hourlyBreakdown(\DateTimeImmutable $since, string|array|null $path = null): array;

    /**
     * @param string|list<string>|null $path
     *
     * @return array<mixed>
     */
    #[BackendOnly(['doctrine', 'eloquent'], 'native SQL: SUM() grouped by the hour bucket and source')]
    public function hourlyBreakdownBySource(\DateTimeImmutable $since, string|array|null $path = null): array;
}
