<?php

namespace Base\Screen\Builder;

use Base\Contract\Runtime;
use Base\Contract\Tokens;
use Base\Contract\Urls;
use Base\Entity\Thread;
use Base\Entity\User;
use Base\Entity\User\Connection;
use Base\Entity\User\Notification as NotificationEntity;
use Base\Screen\Account\AccountSummary;
use Base\Screen\Account\Action;
use Base\Screen\Account\Member;
use Base\Screen\Account\Notification;
use Base\Screen\Account\NotificationsPage;
use Base\Screen\Account\Passkey;
use Base\Screen\Account\ProfilePage;
use Base\Screen\Account\Provider;
use Base\Screen\Account\Session;
use Base\Screen\Account\SettingsPage;
use Base\Screen\Account\TwoFactor;
use Base\Screen\Link;
use Base\Screen\Shape;
use Base\Service\SecurityPolicy;
use Psr\Clock\ClockInterface;

/**
 * The account's pages as view models (plan "omnibase agnostique", C5): a
 * member's profile, the account's settings, the member's notifications, from
 * omnibase's entities (a User, its threads and passkeys, its connections, its
 * notifications) and the site's security policy. Framework-free: the
 * addresses through the Urls contract, the forms' tokens through Tokens (the
 * ones given, else Runtime's), "now" from a PSR-20 clock; what only the
 * framework knows (an avatar's thumbnail, the providers available, web push)
 * the caller passes in.
 *
 * Each page asks the policy and the entities what the template of before
 * asked them, so the pages print the same.
 */
final class Accounts
{
    /** How many of a member's threads their profile lists. */
    public const RECENT = 5;

    /** The providers a settings page offers, by name: their label. */
    public const PROVIDERS = ['google' => 'Google'];

    public function __construct(
        private readonly ?SecurityPolicy $policy = null,
        private readonly ?Urls $urls = null,
        private readonly ?Tokens $tokens = null,
        private readonly ?ClockInterface $clock = null,
    ) {
    }

    /**
     * A member as their profile shows them.
     *
     * @param string|null $avatar the stored avatar's address at 128 px; null when the account has none
     */
    public function member(User $user, ?string $avatar = null): Member
    {
        return new Member(
            id: (int) $user->getId(),
            name: (string) $user,
            avatar: $avatar,
            avatarHtml: method_exists($user, 'getFormattedAvatar') ? (string) $user->getFormattedAvatar() : null,
            roles: array_values(array_map('strval', $user->getRoles())),
            online: $user->isOnline(),
            active: $user->isActive(),
            registeredAt: Shape::moment($user->getCreatedAt()),
            lastSeenAt: Shape::moment($user->getUpdatedAt()),
            threads: \count($user->getThreads()),
            likes: \count($user->getLikes()),
        );
    }

    /**
     * A member's profile.
     *
     * @param bool $own   the viewer is that member
     * @param bool $admin the viewer is an administrator (sees the account's summary, edits the profile)
     */
    public function profile(User $user, bool $own = false, bool $admin = false, ?string $avatar = null): ProfilePage
    {
        $account = $own || $admin;

        return new ProfilePage(
            member: $this->member($user, $avatar),
            own: $own,
            recent: $this->recent($user),
            account: $account ? $this->summary($user) : null,
            editUrl: $account ? $this->urls()->path('user_profileEdit', ['id' => $user->getId()]) : null,
            settingsUrl: $own ? $this->urls()->path('user_settings') : null,
            logoutUrl: $own ? $this->urls()->path('security_logout') : null,
        );
    }

    /** @return list<Link> a member's latest published threads with a title, newest first (by publication, else creation) */
    public function recent(User $user): array
    {
        $threads = array_filter(iterator_to_array($user->getThreads(), false), static fn (Thread $thread) => $thread->isPublished() && $thread->getTitle());
        uasort($threads, static fn (Thread $a, Thread $b) => ($b->getPublishedAt() ?? $b->getCreatedAt()) <=> ($a->getPublishedAt() ?? $a->getCreatedAt()));

        return array_map(static fn (Thread $thread) => new Link(
            (string) $thread->getTitle(),
            (string) $thread->__toLink(),
            Stories::state($thread->getState()),
            Shape::moment($thread->getPublishedAt() ?? $thread->getCreatedAt()),
        ), \array_slice(array_values($threads), 0, self::RECENT));
    }

    /** An account's security at a glance, for its owner or an administrator. */
    public function summary(User $user): AccountSummary
    {
        return new AccountSummary(
            twoFactor: $this->policy?->isTwoFactorAvailable() ? $this->policy->hasSecondFactor($user) : null,
            passkeys: $this->policy?->arePasskeysAvailable() ? \count($user->getPasskeys()) : null,
            identityProvider: $user->getIdentityProvider(),
            locale: $user->getLocale(),
            timezone: $user->getTimezone(),
        );
    }

    /**
     * The account's settings; for nobody signed in, its title alone.
     *
     * @param iterable<Connection>  $sessions  the sessions listed, newest first
     * @param array<string, bool>   $available whether each provider (self::PROVIDERS) can be linked on this site
     */
    public function settings(?User $user, iterable $sessions = [], array $available = []): SettingsPage
    {
        if (null === $user) {
            return new SettingsPage();
        }
        $policy = $this->policy;

        $twoFactor = null;
        if ($policy?->isTwoFactorAvailable()) {
            $mandatory = $policy->isTwoFactorMandatory($user);
            $totp = $user->isTotpAuthenticationEnabled();
            $email = $user->isEmailAuthEnabled();
            $emailLocked = $email && !$policy->canDisableTwoFactor($user) && !$totp;
            $twoFactor = new TwoFactor(
                mandatory: $mandatory,
                enrolmentRequired: $mandatory && !$policy->hasSecondFactor($user),
                totp: $totp,
                totpEnable: !$totp && $policy->canEnableTwoFactor($user) ? $this->urls()->path('user_settings_2fa') : null,
                totpDisable: $totp && $policy->canDisableTwoFactor($user) ? $this->action('user_settings_2fa_disable', [], '2fa_disable') : null,
                email: $email,
                emailLocked: $emailLocked,
                emailToggle: !$emailLocked && $policy->canChangeCredentials($user) ? $this->action('user_settings_2fa_email_toggle', [], '2fa_email') : null,
                backupCodes: $totp ? $user->getBackupCodesCount() : null,
            );
        }

        $passkeys = null;
        if ($policy?->arePasskeysAvailable()) {
            $passkeys = [];
            foreach ($user->getPasskeys() as $passkey) {
                $id = (int) $passkey->getId();
                $passkeys[] = new Passkey(
                    id: $id,
                    rename: $this->action('user_settings_passkey_rename', ['id' => $id], 'passkey_rename_'.$id),
                    delete: $this->action('user_settings_passkey_delete', ['id' => $id], 'passkey_delete_'.$id),
                    label: $passkey->getLabel(),
                    addedAt: Shape::moment($passkey->getCreatedAt()),
                    lastUsedAt: Shape::moment($passkey->getLastUsedAt()),
                );
            }
        }

        $providers = [];
        foreach (self::PROVIDERS as $name => $label) {
            $connected = $name === $user->getIdentityProvider();
            if ($connected || ($available[$name] ?? false)) {
                $providers[] = new Provider($name, $label, $connected, $connected ? null : $this->path('oauth_'.$name));
            }
        }

        $list = [];
        foreach ($sessions as $session) {
            $valid = $session->isValid();
            $list[] = new Session(
                id: (int) $session->getId(),
                agent: $session->getAgent(),
                label: $session->getAgentLabel(),
                ips: array_values(array_map('strval', $session->getIpList() ?? [])),
                updatedAt: Shape::moment($session->getUpdatedAt()),
                valid: $valid,
                logout: $valid ? $this->action('user_settings_session_logout', ['id' => $session->getId()], 'session_logout') : null,
            );
        }

        return new SettingsPage(
            name: (string) $user,
            demo: (bool) $policy?->isDemoAccount($user),
            twoFactor: $twoFactor,
            passkeys: $passkeys,
            identifier: $user->getUserIdentifier(),
            providers: $providers,
            sessions: $list,
            language: $user->getLocale(),
            timezone: $user->getTimezone(),
        );
    }

    /**
     * The member's notifications.
     *
     * @param iterable<NotificationEntity>                   $notifications newest first
     * @param \Closure(NotificationEntity): ?string|null     $adminUrl      the back-office address of what one is about
     * @param \DateTimeZone|null                             $timezone      the page's (the days are its days); the machine's when none is given
     */
    public function notifications(iterable $notifications, int $unread, bool $unreadOnly = false, bool $push = false, ?string $pushKey = null, ?\Closure $adminUrl = null, ?\DateTimeZone $timezone = null): NotificationsPage
    {
        $urls = $this->urls();
        $list = [];
        foreach ($notifications as $notification) {
            $list[] = $this->notification($notification, $adminUrl ? $adminUrl($notification) : null, $timezone);
        }

        return new NotificationsPage(
            notifications: $list,
            unread: $unread,
            unreadOnly: $unreadOnly,
            allUrl: $urls->path('user_notifications'),
            token: $this->tokens()->csrf('notifications'),
            readUrl: $urls->path('user_notifications_read'),
            latestUrl: $urls->path('user_notifications_latest'),
            deleteUrl: $urls->path('user_notifications_delete'),
            subscribeUrl: $urls->path('push_subscribe'),
            unsubscribeUrl: $urls->path('push_unsubscribe'),
            push: $push,
            pushKey: $push ? (string) $pushKey : null,
        );
    }

    public function notification(NotificationEntity $notification, ?string $adminUrl = null, ?\DateTimeZone $timezone = null): Notification
    {
        $content = $notification->getContent();
        $sentAt = Shape::moment($notification->getSentAt());

        return new Notification(
            id: (int) $notification->getId(),
            title: $notification->getTitle() ?: $notification->getSubject(),
            content: $content ? strip_tags($content) : null,
            url: $notification->getUrl(),
            adminUrl: $adminUrl,
            importance: $notification->getImportance(),
            isRead: $notification->isRead(),
            sentAt: $sentAt,
            group: $this->group($sentAt, $timezone),
        );
    }

    /**
     * The day a moment falls in, by the clock, in the page's time zone: today,
     * yesterday, within the week, earlier (and earlier for no moment).
     */
    public function group(?string $moment, ?\DateTimeZone $timezone = null): string
    {
        if (null === $moment) {
            return 'earlier';
        }
        $timezone ??= new \DateTimeZone(date_default_timezone_get());
        $now = ($this->clock ?? Runtime::clock())->now()->setTimezone($timezone);
        $day = (new \DateTimeImmutable($moment))->setTimezone($timezone)->format('Y-m-d');

        return match (true) {
            $day === $now->format('Y-m-d') => 'today',
            $day === $now->modify('-1 day')->format('Y-m-d') => 'yesterday',
            $day > $now->modify('-7 days')->format('Y-m-d') => 'week',
            default => 'earlier',
        };
    }

    private function action(string $route, array $parameters, string $intent): Action
    {
        return new Action($this->urls()->path($route, $parameters), $this->tokens()->csrf($intent));
    }

    /** A route's address, null when the application has no such route. */
    private function path(string $route): ?string
    {
        try {
            return $this->urls()->path($route);
        } catch (\Throwable) {
            return null;
        }
    }

    private function urls(): Urls
    {
        return $this->urls ?? Runtime::urls();
    }

    private function tokens(): Tokens
    {
        return $this->tokens ?? Runtime::tokens();
    }
}
