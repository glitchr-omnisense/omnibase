<?php

namespace Base\Repository;

/**
 * The core's repositories by their interfaces (audit "lesringostars face au
 * master plan", steps R15 and R27): what the code of a site and of the core
 * type-hints, Doctrine's repository behind - one list, which Symfony's
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
        Threads::class => ThreadRepository::class,
        Thread\Tags::class => Thread\TagRepository::class,
        User\Notifications::class => User\NotificationRepository::class,
        Layout\Settings::class => Layout\SettingRepository::class,
        // Step R28: what the core's code on lesringostars' path asked getRepository() for.
        ThreadTranslations::class => ThreadIntlRepository::class,
        Extension\TrashBalls::class => Extension\TrashBallRepository::class,
        Extension\Revisions::class => Extension\RevisionRepository::class,
        Layout\Widgets::class => Layout\WidgetRepository::class,
        Layout\Widget\Slots::class => Layout\Widget\SlotRepository::class,
        Layout\ImageCrops::class => Layout\ImageCropRepository::class,
        Layout\Redirections::class => Layout\RedirectionRepository::class,
        Layout\Semantics::class => Layout\SemanticRepository::class,
        Thread\Likes::class => Thread\LikeRepository::class,
        Thread\Comments::class => Thread\CommentRepository::class,
        Thread\Mentions::class => Thread\MentionRepository::class,
        User\Connections::class => User\ConnectionRepository::class,
        User\Passkeys::class => User\PasskeyRepository::class,
        User\Tokens::class => User\TokenRepository::class,
        User\PushSubscriptions::class => User\PushSubscriptionRepository::class,
        Analytics\PageViews::class => Analytics\PageViewRepository::class,
        Analytics\Visits::class => Analytics\VisitRepository::class,
        Analytics\PageVisits::class => Analytics\PageVisitRepository::class,
        Hours\ScopedWeeks::class => Hours\ScopedWeekRepository::class,
        Hours\SpecialDays::class => Hours\SpecialDayRepository::class,
        Hours\WeekDays::class => Hours\WeekDayHoursRepository::class,
        // The entities of a class named at run time (Base\Database\Repository\Repositories), Doctrine's answer.
        \Base\Database\Repository\Repositories::class => \Base\Bridge\Doctrine\DoctrineRepositories::class,
    ];
}
