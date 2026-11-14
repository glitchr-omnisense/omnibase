<?php

namespace Base\Repository;

/**
 * The core's repositories by their interfaces (audit "lesringostars face au
 * master plan", steps R15 and R27): what the code of a site and of the core
 * type-hints, Doctrine's repository behind (omnibridge/doctrine's, by its own name since 6.0) - one list, which Symfony's
 * container aliases (config/services/database.php) and omnibridge/laravel
 * binds, as a site's own repositories declare theirs by #[AsAlias]. The
 * users' is the application's (App\Repository\UserRepository: the site's
 * own, else omnibase's under that name).
 */
final class Aliases
{
    /** @var array<class-string, string> interface => repository */
    public const INTERFACES = [
        Users::class => 'App\Repository\UserRepository',
        Threads::class => \Omnibridge\Doctrine\Omnibase\Repository\ThreadRepository::class,
        Thread\Tags::class => \Omnibridge\Doctrine\Omnibase\Repository\Thread\TagRepository::class,
        User\Notifications::class => \Omnibridge\Doctrine\Omnibase\Repository\User\NotificationRepository::class,
        Layout\Settings::class => \Omnibridge\Doctrine\Omnibase\Repository\Layout\SettingRepository::class,
        // Step R28: what the core's code on lesringostars' path asked getRepository() for.
        ThreadTranslations::class => \Omnibridge\Doctrine\Omnibase\Repository\ThreadIntlRepository::class,
        Extension\TrashBalls::class => \Omnibridge\Doctrine\Omnibase\Repository\Extension\TrashBallRepository::class,
        Extension\Revisions::class => \Omnibridge\Doctrine\Omnibase\Repository\Extension\RevisionRepository::class,
        Layout\Widgets::class => \Omnibridge\Doctrine\Omnibase\Repository\Layout\WidgetRepository::class,
        Layout\Widget\Slots::class => \Omnibridge\Doctrine\Omnibase\Repository\Layout\Widget\SlotRepository::class,
        Layout\ImageCrops::class => \Omnibridge\Doctrine\Omnibase\Repository\Layout\ImageCropRepository::class,
        Layout\Redirections::class => \Omnibridge\Doctrine\Omnibase\Repository\Layout\RedirectionRepository::class,
        Layout\Semantics::class => \Omnibridge\Doctrine\Omnibase\Repository\Layout\SemanticRepository::class,
        Thread\Likes::class => \Omnibridge\Doctrine\Omnibase\Repository\Thread\LikeRepository::class,
        Thread\Comments::class => \Omnibridge\Doctrine\Omnibase\Repository\Thread\CommentRepository::class,
        Thread\Mentions::class => \Omnibridge\Doctrine\Omnibase\Repository\Thread\MentionRepository::class,
        User\Connections::class => \Omnibridge\Doctrine\Omnibase\Repository\User\ConnectionRepository::class,
        User\Passkeys::class => \Omnibridge\Doctrine\Omnibase\Repository\User\PasskeyRepository::class,
        // Step F2d: what Base\Auth\UserIdentities asked getRepository() for.
        User\Identities::class => \Omnibridge\Doctrine\Omnibase\Repository\User\IdentityRepository::class,
        User\Tokens::class => \Omnibridge\Doctrine\Omnibase\Repository\User\TokenRepository::class,
        User\PushSubscriptions::class => \Omnibridge\Doctrine\Omnibase\Repository\User\PushSubscriptionRepository::class,
        Analytics\PageViews::class => \Omnibridge\Doctrine\Omnibase\Repository\Analytics\PageViewRepository::class,
        Analytics\Visits::class => \Omnibridge\Doctrine\Omnibase\Repository\Analytics\VisitRepository::class,
        Analytics\PageVisits::class => \Omnibridge\Doctrine\Omnibase\Repository\Analytics\PageVisitRepository::class,
        Hours\ScopedWeeks::class => \Omnibridge\Doctrine\Omnibase\Repository\Hours\ScopedWeekRepository::class,
        Hours\SpecialDays::class => \Omnibridge\Doctrine\Omnibase\Repository\Hours\SpecialDayRepository::class,
        Hours\WeekDays::class => \Omnibridge\Doctrine\Omnibase\Repository\Hours\WeekDayHoursRepository::class,
        // Plan "omnibase agnostic", step E2': what omnibase/admin's dashboard typed on its class for.
        User\Complaints::class => \Omnibridge\Doctrine\Omnibase\Repository\User\ComplaintRepository::class,
        // The entities of a class named at run time (Base\Database\Repository\Repositories), Doctrine's answer.
        \Base\Database\Repository\Repositories::class => \Omnibridge\Doctrine\Omnibase\DoctrineRepositories::class,
    ];
}
