<?php

namespace Base\Twig\Extension;

use Base\Service\Analytics;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Thin Twig-side wrapper around Base\Service\Analytics - lets any template
 * (the admin sidebar widget, a future public "N readers this week" badge,
 * ...) reach the counters directly, without every consumer needing its own
 * controller/Twig-global plumbing just to display a number.
 */
final class AnalyticsTwigExtension extends AbstractExtension
{
    public function __construct(private readonly Analytics $analytics)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('analytics_page_views', [$this, 'pageViews']),
            new TwigFunction('analytics_unique_visitors', [$this, 'uniqueVisitors']),
            new TwigFunction('analytics_unique_users', [$this, 'uniqueUsers']),
            new TwigFunction('analytics_summary', [$this, 'summary']),
            new TwigFunction('analytics_daily_breakdown', [$this, 'dailyBreakdown']),
            new TwigFunction('analytics_week_over_week_change', [$this, 'weekOverWeekChange']),
        ];
    }

    /**
     * {{ analytics_page_views() }} - site-wide, all-time
     * {{ analytics_page_views(window: '7d') }} - site-wide, last 7 days
     * {{ analytics_page_views(app.request.pathInfo, 'today') }} - this page, today
     */
    public function pageViews(?string $path = null, ?string $window = null): int
    {
        return $this->analytics->pageViews($path, $window);
    }

    /**
     * {{ analytics_unique_visitors('30d') }}
     */
    public function uniqueVisitors(?string $window = null): int
    {
        return $this->analytics->uniqueVisitors($window);
    }

    /**
     * {{ analytics_unique_users('today') }}
     */
    public function uniqueUsers(?string $window = null): int
    {
        return $this->analytics->uniqueUsers($window);
    }

    /**
     * {% set stats = analytics_summary() %}
     * {{ stats.today.pageViews }} / {{ stats['7d'].uniqueVisitors }} / ...
     */
    public function summary(): array
    {
        return $this->analytics->summary();
    }

    /**
     * {% set series = analytics_daily_breakdown(14) %}
     * {% set series = analytics_daily_breakdown(null) %} - all time
     */
    public function dailyBreakdown(?int $days = 14): array
    {
        return $this->analytics->dailyBreakdown($days);
    }

    /**
     * {% set change = analytics_week_over_week_change() %}
     * {{ change.pageViews }}% vs last week
     */
    public function weekOverWeekChange(): array
    {
        return $this->analytics->weekOverWeekChange();
    }
}
