<?php

/*
 * The account's screens where there is no framework (plan "omnibase
 * agnostique", C5, the account's part): run by docker/bin/bare for
 * tests/BareTest.php with Composer's autoloader alone - no kernel, no
 * container, no bundle - and bare reports every class and file of the
 * framework loaded on the way (none may be). The contracts the builder needs
 * are plain PHP here (Urls, Tokens, a clock); omnibase's own Notification -
 * made without its constructor, which asks the framework's notifier - becomes
 * the notifications' page, and the profile's and the settings' models are
 * made as their builder makes them, as a controller hands them to a view
 * bridge. Returns their props.
 *
 * Not here: Accounts::profile() and settings() from a Base\Entity\User. The
 * entity implements Symfony Security's UserInterface and scheb/2fa's
 * interfaces, so loading it loads them (plan "omnibase agnostique", the
 * remainders after C3e); the builder and the models do not, and the harness's
 * AccountPagesSnapshotTest builds both pages from real accounts.
 */

use Base\Contract\Runtime;
use Base\Contract\Tokens;
use Base\Contract\Urls;
use Base\Entity\User\Notification;
use Base\Screen\Account\AccountSummary;
use Base\Screen\Account\Action;
use Base\Screen\Account\Member;
use Base\Screen\Account\ProfilePage;
use Base\Screen\Account\Session;
use Base\Screen\Account\SettingsPage;
use Base\Screen\Account\TwoFactor;
use Base\Screen\Builder\Accounts;
use Base\Screen\Confidential;
use Base\Screen\Link;
use Base\Screen\Shape;
use Base\Service\Model\ClassAliases;
use Psr\Clock\ClockInterface;

ClassAliases::register();

$urls = new class implements Urls {
    public function path(string $name, array $parameters = []): string
    {
        return '/'.str_replace('_', '/', $name).($parameters ? '/'.implode('/', $parameters) : '');
    }

    public function url(string $name, array $parameters = []): string
    {
        return 'https://example.org'.$this->path($name, $parameters);
    }

    public function asset(string $path): string
    {
        return '/'.ltrim($path, '/');
    }
};
$tokens = new class implements Tokens {
    public function csrf(string $intent): string
    {
        return 'token-of-'.$intent;
    }
};
$clock = new class implements ClockInterface {
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-04-11 10:00:00', new DateTimeZone('UTC'));
    }
};
// The entity without its constructor (it asks the framework's notifier): its fields set as Doctrine would.
$make = static function (string $class, array $fields): object {
    $object = (new ReflectionClass($class))->newInstanceWithoutConstructor();
    foreach ($fields as $name => $value) {
        for ($reflection = new ReflectionClass($object); !$reflection->hasProperty($name); $reflection = $reflection->getParentClass()) {
        }
        $reflection->getProperty($name)->setValue($object, $value);
    }

    return $object;
};
$at = static fn (string $moment): DateTime => new DateTime($moment, new DateTimeZone('UTC'));

$notifications = [
    $make(Notification::class, ['id' => 5, 'title' => 'Nouvelle réponse', 'subject' => '', 'content' => '<p>Paul a <b>répondu</b>.</p>', 'url' => '/bbs/le-club-de-lecture', 'importance' => 'default', 'isRead' => false, 'sentAt' => $at('2026-04-10 18:05:00')]),
    $make(Notification::class, ['id' => 4, 'title' => '', 'subject' => 'Bienvenue', 'content' => '', 'url' => null, 'importance' => 'low', 'isRead' => true, 'sentAt' => $at('2026-03-30 07:00:00')]),
];

$accounts = new Accounts(null, $urls, $tokens, $clock);

// The profile's and the settings' models, as Accounts makes them of an account.
$profile = new ProfilePage(
    member: new Member(12, 'anne@example.org', '/uploads/avatars/anne.jpg?w=128', roles: ['ROLE_USER'], registeredAt: Shape::moment($at('2025-11-02 10:20:00')), threads: 2),
    own: true,
    recent: [new Link('Le club de lecture', '/bbs/le-club-de-lecture', 'STATE_PUBLISH', Shape::moment($at('2026-04-08 18:00:00')))],
    account: new AccountSummary(twoFactor: true, passkeys: 0, locale: 'fr-FR', timezone: 'Europe/Paris'),
    editUrl: $urls->path('user_profileEdit', ['id' => 12]),
);
$settings = new SettingsPage(
    name: 'anne@example.org',
    twoFactor: new TwoFactor(email: true, emailToggle: new Action($urls->path('user_settings_2fa_email_toggle'), $tokens->csrf('2fa_email'))),
    passkeys: [],
    sessions: [new Session(3, label: 'Safari - macOS', ips: ['192.0.2.10'], valid: true, logout: new Action($urls->path('user_settings_session_logout', ['id' => 3]), $tokens->csrf('session_logout')))],
    language: 'fr-FR',
);

$answers = [
    'profile' => $profile->toArray(),
    'settings' => $settings->toArray(),
    'confidential' => [$settings instanceof Confidential, $profile instanceof Confidential],
    'notifications' => $accounts->notifications($notifications, 1, timezone: new DateTimeZone('UTC'))->toArray(),
    'groups' => array_map(static fn (string $moment) => $accounts->group($moment, new DateTimeZone('Asia/Tokyo')), ['2026-04-11T01:00:00+00:00', '2026-04-10T14:00:00+00:00', '2026-04-05T12:00:00+00:00', '2026-04-01T12:00:00+00:00']),
    'json' => json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
];

Runtime::reset();

return $answers;
