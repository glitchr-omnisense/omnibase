<?php

namespace Base\Service;

/**
 * A source of "worth marking on a timeline" events (a deploy, a campaign
 * launch, ...), collected by base-bundle-admin's TimelineEventRegistry and
 * rendered as annotations on the analytics dashboard chart. Lives here
 * (not base-bundle-admin) for the same reason Analytics does: it's a
 * domain concept, not an admin-UI-specific one - a future non-admin
 * consumer (a public changelog widget, a CLI report) could reuse it
 * without depending on the admin package.
 *
 * No implementation ships with this interface - any bundle or app service
 * can supply one by implementing it (self-registers automatically, see
 * AdminBundle::build()'s registerForAutoconfiguration()).
 */
interface TimelineEventProviderInterface
{
    /**
     * @param \DateTimeImmutable $since
     * @param ?\DateTimeImmutable $until null = up to today
     *
     * @return array<int, array{date: string, title: string, description?: ?string, color?: ?string, url?: ?string}>
     *         'date' is Y-m-d, same granularity as Analytics::dailyBreakdown()'s own rows.
     *         'url', when present, is what turns this event into a clickable
     *         marker on the chart (see admin-charts.js's buildAnnotations())
     *         instead of a plain dashed line - an event with somewhere real
     *         to send an admin (an article's own page, here) is worth
     *         making directly navigable, not just visible.
     */
    public function getTimelineEvents(\DateTimeImmutable $since, ?\DateTimeImmutable $until = null): array;
}
