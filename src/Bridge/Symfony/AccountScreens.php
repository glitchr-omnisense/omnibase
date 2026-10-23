<?php

namespace Base\Bridge\Symfony;

use Base\Entity\User;
use Base\Entity\User\Notification;
use Base\Screen\Account\NotificationsPage;
use Base\Screen\Account\ProfilePage;
use Base\Screen\Account\SettingsPage;
use Base\Screen\Builder\Accounts;
use Base\Twig\Extension\PictureTwigExtension;
use Base\Twig\Extension\NotificationTwigExtension;
use Base\Twig\Extension\OAuthTwigExtension;
use Omnibridge\Contract\Access;
use Twig\Environment;
use Twig\Extension\CoreExtension;

/**
 * The account's screens under Symfony (plan "omnibase agnostique", C5): what
 * the framework-free builder (Base\Screen\Builder\Accounts) cannot know, asked
 * where the templates of before asked it - the viewer and their roles (the
 * Access contract: `app.user`, `is_granted`), an avatar's thumbnail
 * (`|thumbnail`, PictureTwigExtension), the providers one may link
 * (`oauth_available()`), web push and the back-office address of a
 * notification (`push_available()`, `push_vapid_key()`, NotificationTwigExtension),
 * Twig's time zone (the days of the notifications' groups) -, handed to the
 * builder. What the account's controllers and the Twig function
 * `account_profile()` (a host page's) call; a Twig runtime, so that it may
 * read Twig's extensions without a circular service.
 */
class AccountScreens
{
    /** The width and height of a profile's avatar. */
    public const AVATAR = 128;

    public function __construct(
        private readonly Accounts $accounts,
        private readonly Environment $twig,
        private readonly ?Access $access = null,
    ) {
    }

    /** A member's profile, as the visitor asking sees it. */
    public function profile(User $user): ProfilePage
    {
        $viewer = $this->access?->getUser();
        $own = $viewer instanceof User && null !== $viewer->getId() && $viewer->getId() === $user->getId();

        return $this->accounts->profile($user, $own, (bool) $this->access?->isGranted('ROLE_ADMIN'), $this->avatar($user));
    }

    /** @param iterable<\Base\Entity\User\Connection> $sessions */
    public function settings(?User $user, iterable $sessions = []): SettingsPage
    {
        $oauth = $this->extension(OAuthTwigExtension::class);
        $available = [];
        foreach (array_keys(Accounts::PROVIDERS) as $name) {
            $available[$name] = (bool) $oauth?->isAvailable($name);
        }

        return $this->accounts->settings($user, $sessions, $available);
    }

    /** @param iterable<Notification> $notifications */
    public function notifications(iterable $notifications, int $unread, bool $unreadOnly = false): NotificationsPage
    {
        $bell = $this->extension(NotificationTwigExtension::class);
        $push = (bool) $bell?->pushAvailable();

        return $this->accounts->notifications(
            $notifications,
            $unread,
            $unreadOnly,
            $push,
            $push ? $bell->pushKey() : null,
            $bell ? static fn (Notification $notification): ?string => $bell->adminUrl($notification) : null,
            $this->twig->getExtension(CoreExtension::class)->getTimezone(),
        );
    }

    /** The stored avatar's thumbnail, as `|thumbnail(128, 128)` gave it; null for none. */
    private function avatar(User $user): ?string
    {
        if (!$user->getAvatar()) {
            return null;
        }
        $thumbnail = $this->extension(PictureTwigExtension::class)?->thumbnail([], $user->getAvatar(), self::AVATAR, self::AVATAR);

        return \is_string($thumbnail) ? $thumbnail : '';
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    private function extension(string $class): ?object
    {
        return $this->twig->hasExtension($class) ? $this->twig->getExtension($class) : null;
    }
}
