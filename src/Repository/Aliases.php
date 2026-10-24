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
    ];
}
