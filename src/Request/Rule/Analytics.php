<?php

namespace Base\Request\Rule;

use Base\Repository\Users;
use Base\Request\Page;
use Base\Service\TranslatorInterface;

/**
 * The back office's figures (plan "omnibase agnostic", step F5e, slice 2; formerly omnibridge/symfony's
 * AnalyticsSubscriber): on its main requests (not the profiler's), the accounts online - active within the
 * online delay, the visitor aside - and those of them active now, as the views' globals `user_analytics`
 * (labels) and `user_manager` (the accounts); and Google Analytics' basics, where the host has them, as
 * `user_analytics.google`. The host gives its globals and puts back the ones answered.
 */
final class Analytics
{
    public function __construct(private readonly Users $users, private readonly TranslatorInterface $translator)
    {
    }

    public static function applies(Page $page, bool $main): bool
    {
        return $main && !$page->isProfiler() && $page->isAdmin();
    }

    /**
     * @param array<string, mixed> $globals the views' globals as they are
     *
     * @return array{user_analytics: array<string, mixed>, user_manager: array<string, mixed>}
     */
    public function users(?object $user, int $delay, array $globals): array
    {
        $online = null !== $user && method_exists($user, 'getId') ? $this->users->activeWithin($delay, $user->getId()) : $this->users->activeWithin($delay);
        $active = array_filter($online, static fn ($u) => $u->isActive());

        $analytics = array_merge($globals['user_analytics'] ?? [], ['label' => $this->translator->trans('@messages.user_analytics.label', [\count($active)])]);
        $manager = [];
        if (\count($online)) {
            $manager = ['online' => $online, 'active' => $active];
            $analytics = array_merge($analytics, ['default' => [
                'active' => ['label' => $this->translator->trans('@messages.user_analytics.active_users', [\count($active)]), 'icon' => 'fa-solid  fa-user'],
                'online' => ['label' => $this->translator->trans('@messages.user_analytics.online_users', [\count($online)]), 'icon' => 'fa-solid  fa-user-clock'],
            ]]);
        }

        return ['user_analytics' => $analytics, 'user_manager' => $manager];
    }

    public const GOOGLE = [
        'users' => ['@google_users', 'fa-solid  fa-user'],
        'users_1day' => ['@google_users_1day', 'fa-solid  fa-user-clock'],
        'views' => ['@google_analytics.views', 'fa-regular  fa-eye'],
        'views_1day' => ['@google_analytics.views_1day', 'fa-solid  fa-eye'],
        'sessions' => ['@google_analytics.sessions', 'fa-solid  fa-stopwatch'],
        'bounces_1day' => ['@google_analytics.bounces_1day', 'fa-solid  fa-meteor'],
    ];

    /**
     * @param array<string, mixed> $basics  Google Analytics' (users, users_1day, views, views_1day, sessions, bounces_1day)
     * @param array<string, mixed> $globals the views' globals as they are
     *
     * @return array<string, mixed> `user_analytics` with its `google` entries
     */
    public function google(array $basics, array $globals): array
    {
        $entries = [];
        foreach (self::GOOGLE as $key => [$label, $icon]) {
            if ($basics[$key] ?? null) {
                $entries[$key] = ['label' => $this->translator->trans($label, [$basics[$key]]), 'icon' => $icon];
            }
        }

        return array_merge($globals['user_analytics'] ?? [], ['google' => $entries]);
    }
}
