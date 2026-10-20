<?php

namespace Tests\Base\Screen;

use Base\Entity\User\Connection;
use Base\Entity\User\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * The account's pages, as the host's kernel answers them, equal to the HTML
 * recorded in tests/Screen/snapshots/ before the pages went through view
 * models (plan "omnibase agnostique", C5): a member's profile seen by a
 * visitor, by its owner and by an administrator, the same template rendered
 * by a host page with the user alone (lesringostars' /profile/of/{username}),
 * the settings signed out, with an e-mail code and sessions, and with an
 * authenticator app, the notifications (all, the unread ones, none).
 *
 * Every moment is fixed (the accounts', the sessions', the notifications'),
 * so the pages are the same from one run to the next; the forms' CSRF tokens,
 * drawn anew at each request, are set aside. A snapshot missing is written,
 * and the test then says so (incomplete): ACCOUNT_SNAPSHOT_RECORD=1 writes
 * them all again.
 */
final class AccountPagesSnapshotTest extends KernelTestCase
{
    private const SNAPSHOTS = __DIR__.'/snapshots';
    private const TOPIC = 'Base\\Forum\\Entity\\Topic';

    /** @var list<array{0: class-string, 1: int}> what the test wrote, removed after */
    private array $created = [];

    /** @var array<string, int> */
    private array $ids = [];

    protected function setUp(): void
    {
        $_SERVER['KERNEL_CLASS'] ??= $_ENV['KERNEL_CLASS'] ?? 'App\\Kernel';
        if (!class_exists($_SERVER['KERNEL_CLASS']) || !class_exists('App\\Entity\\User') || !class_exists(self::TOPIC)) {
            self::markTestSkipped('Needs the omnibase harness (its kernel, its User, omnibase/forum\'s topics as threads with an address).');
        }
        self::bootKernel();
        $_SERVER['REMOTE_ADDR'] ??= '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] ??= 'phpunit';
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
        self::bootKernel();
        $em = $this->em();
        // Removed for good: what is trashable goes to the bin first, and is gone the second time (the trash filter off to find it there).
        for ($pass = 0; $pass < 2; ++$pass) {
            if (1 === $pass && $em->getFilters()->isEnabled('trash_filter')) {
                $em->getFilters()->disable('trash_filter');
            }
            foreach (array_reverse($this->created) as [$class, $id]) {
                if (null !== $entity = $em->find($class, $id)) {
                    $em->remove($entity);
                    $em->flush();
                }
            }
            $em->clear();
        }
        $users = array_map(static fn ($entry) => $entry[1], array_filter($this->created, static fn ($entry) => 'App\\Entity\\User' === $entry[0]));
        if ($users && \in_array('userProfile', $em->getConnection()->createSchemaManager()->listTableNames(), true)) {
            $em->getConnection()->executeStatement('DELETE FROM userProfile WHERE id IN (?)', [$users], [\Doctrine\DBAL\ArrayParameterType::INTEGER]);
        }
        $this->created = [];
        parent::tearDown();
    }

    public function testTheAccountPagesAreThoseRecorded(): void
    {
        $this->seed();
        $member = $this->user('member');
        $admin = $this->user('admin');
        $id = $this->ids['member'];

        $pages = [
            'profile-visitor' => $this->page('/profile/'.$id),
            'profile-owner' => $this->page('/profile/'.$id, $member),
            'profile-admin' => $this->page('/profile/'.$id, $admin),
            'profile-host' => $this->hostProfile(),
            'settings-signed-out' => $this->page('/settings'),
            'settings' => $this->page('/settings', $member),
            'settings-totp' => $this->page('/settings', $admin),
            'notifications' => $this->page('/notifications', $member),
            'notifications-unread' => $this->page('/notifications?unread=1', $member),
            'notifications-none' => $this->page('/notifications', $admin),
        ];

        $written = [];
        foreach ($pages as $name => $html) {
            $file = self::SNAPSHOTS.'/account-'.$name.'.html';
            $html = $this->normalize($html);
            if (!is_file($file) || getenv('ACCOUNT_SNAPSHOT_RECORD')) {
                @mkdir(self::SNAPSHOTS, 0777, true);
                file_put_contents($file, $html);
                $written[] = $name;
                continue;
            }
            self::assertSame(file_get_contents($file), $html, sprintf('The page "%s" is not the one recorded (%s).', $name, $file));
        }
        if ($written) {
            self::markTestIncomplete('Snapshots written: '.implode(', ', $written).'.');
        }
    }

    /**
     * The same pages as data (plan "omnibase agnostique", C5): the frame's and
     * the screen's props together, as a front end asks them - omnibridge/react's
     * protocol where it is installed, else a request asking JSON alone.
     *
     * A visitor's profile carries what its HTML shows that visitor (the header,
     * the threads) and nothing of the account (the pilot's parity rule); the
     * owner's adds the summary and the addresses. The settings' actions carry
     * the token of their own intent; the notifications' groups follow the
     * screen's clock; answers with tokens are stored by no cache.
     */
    public function testTheAccountScreensAreGivenAsData(): void
    {
        $this->seed();
        $member = $this->user('member');
        $id = $this->ids['member'];

        // A visitor: the props are what the page shows them.
        $visitor = $this->props('/profile/'.$id, null, $response);
        $html = $this->page('/profile/'.$id);
        self::assertSame(['locale', 'site', 'links', 'viewer', 'theme', 'member', 'own', 'recent', 'account', 'editUrl', 'settingsUrl', 'logoutUrl'], array_keys($visitor));
        self::assertSame([
            'id' => $id, 'name' => 'anne.membre', 'avatar' => null, 'avatarHtml' => null, 'roles' => ['ROLE_USER'], 'online' => true, 'active' => true,
            'registeredAt' => '2025-11-02T10:20:00+00:00', 'lastSeenAt' => '2026-04-10T19:00:00+00:00', 'threads' => 3, 'likes' => 0,
        ], $visitor['member']);
        self::assertSame([false, null, null, null, null], [$visitor['own'], $visitor['account'], $visitor['editUrl'], $visitor['settingsUrl'], $visitor['logoutUrl']]);
        self::assertSame([
            ['title' => 'Le club de lecture', 'url' => '/bbs/le-club-de-lecture', 'state' => 'STATE_PUBLISH', 'publishedAt' => '2026-04-08T18:00:00+00:00'],
            ['title' => 'La sortie du samedi', 'url' => '/bbs/la-sortie-du-samedi', 'state' => 'STATE_PUBLISH', 'publishedAt' => '2026-04-03T09:30:00+00:00'],
        ], $visitor['recent']);
        // Each value the props carry, the visitor's page prints.
        self::assertSame(\count($visitor['member']['roles']), preg_match_all('~<span class="badge bg-secondary">~', explode('<div class="info">', explode('<div class="roles mb-1">', $html)[1])[0]), 'a badge for each role');
        foreach (['#'.$id.': anne.membre', 'title="Online"', 'Registered on 02 november 2025 at 10:20', 'Last seen on 10 april 2026 at 19:00', '<strong>3</strong> posts', '<strong>0</strong> likes', '/bbs/le-club-de-lecture', '08 april 2026', '/bbs/la-sortie-du-samedi', '03 april 2026'] as $shown) {
            self::assertStringContainsString($shown, $html);
        }
        foreach (['Account security', '/settings', '/profile/'.$id.'/edit/', '/logout'] as $hidden) {
            self::assertStringNotContainsString($hidden, explode('<main>', $html)[1], 'what the props leave out, the page does not show');
        }
        self::assertFalse($response->headers->hasCacheControlDirective('no-store'));

        // Its owner: the account's summary, the addresses; private.
        $owner = $this->props('/profile/'.$id, $member, $response);
        self::assertTrue($owner['own']);
        self::assertSame(['twoFactor' => true, 'passkeys' => 0, 'identityProvider' => null, 'locale' => 'en-GB', 'timezone' => 'UTC'], $owner['account'], 'passkeys counted (0) where they are available');
        self::assertSame(['/profile/'.$id.'/edit/', '/settings'], [$owner['editUrl'], $owner['settingsUrl']]);
        // The sign-out carries the token the firewall's logout asks (Base\Security\SignOut).
        self::assertMatchesRegularExpression('~^/logout\?_csrf_token=[^&]+$~', $owner['logoutUrl']);
        // (Its value is the token manager's: here a stateless one, logout being among this
        // harness's stateless_token_ids; a site's firewall checks it, lesringostars' tests/SignIn.)
        self::assertMatchesRegularExpression('~^/logout\?_csrf_token=[^&]+$~', $owner['links']['logout'], 'the frame\'s too, for someone signed in');
        self::assertSame('/logout', $visitor['links']['logout'], 'a visitor\'s is the bare address: no token, no session');
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertContains('Cookie', $response->getVary());

        // The settings: each action the token of its own intent; stored by no cache.
        $settings = $this->props('/settings', $member, $response);
        self::assertSame(['name', 'demo', 'twoFactor', 'passkeys', 'identifier', 'providers', 'sessions', 'language', 'timezone'], \array_slice(array_keys($settings), 5));
        self::assertSame(['anne.membre', false, [], 'anne.membre@example.org', [], 'en-GB', 'UTC'], [$settings['name'], $settings['demo'], $settings['passkeys'], $settings['identifier'], $settings['providers'], $settings['language'], $settings['timezone']]);
        $twoFactor = $settings['twoFactor'];
        self::assertSame([false, false, false, '/settings/2fa', null, true, false, null], [$twoFactor['mandatory'], $twoFactor['enrolmentRequired'], $twoFactor['totp'], $twoFactor['totpEnable'], $twoFactor['totpDisable'], $twoFactor['email'], $twoFactor['emailLocked'], $twoFactor['backupCodes']]);
        self::assertSame('/settings/2fa/email', $twoFactor['emailToggle']['url']);
        self::assertTrue($this->tokenValid($response, '2fa_email', $twoFactor['emailToggle']['token']));
        self::assertFalse($this->tokenValid($response, '2fa_disable', $twoFactor['emailToggle']['token']), 'one intent\'s token is not another\'s');
        self::assertSame([['Safari - macOS', ['192.0.2.10'], '2026-04-10T08:15:00+00:00', true], ['Chrome - Linux', ['198.51.100.7'], '2026-04-02T21:40:00+00:00', false]], array_map(static fn ($session) => [$session['label'], $session['ips'], $session['updatedAt'], $session['valid']], $settings['sessions']));
        self::assertSame('/settings/sessions/'.$this->ids['session:open'].'/logout', $settings['sessions'][0]['logout']['url']);
        self::assertTrue($this->tokenValid($response, 'session_logout', $settings['sessions'][0]['logout']['token']));
        self::assertNull($settings['sessions'][1]['logout']);
        $this->assertStoredByNoCache($response);
        $totp = $this->props('/settings', $this->user('admin'), $response)['twoFactor'];
        self::assertSame([true, null, 3, '/settings/2fa/disable'], [$totp['totp'], $totp['totpEnable'], $totp['backupCodes'], $totp['totpDisable']['url']]);
        self::assertTrue($this->tokenValid($response, '2fa_disable', $totp['totpDisable']['token']));

        // The notifications: the groups by the screen's clock (the page's time zone, UTC here).
        foreach (['2026-04-10 20:00:00' => ['today', 'yesterday', 'earlier'], '2026-04-11 10:00:00' => ['yesterday', 'week', 'earlier']] as $now => $groups) {
            \Base\Contract\Runtime::set(\Psr\Clock\ClockInterface::class, new class($now) implements \Psr\Clock\ClockInterface {
                public function __construct(private string $now)
                {
                }

                public function now(): \DateTimeImmutable
                {
                    return new \DateTimeImmutable($this->now, new \DateTimeZone('UTC'));
                }
            });
            try {
                $page = $this->props('/notifications', $member, $response);
            } finally {
                \Base\Contract\Runtime::set(\Psr\Clock\ClockInterface::class, null);
            }
            self::assertSame($groups, array_map(static fn ($notification) => $notification['group'], $page['notifications']), $now);
        }
        self::assertSame([$this->ids['notification:unread'], $this->ids['notification:read'], $this->ids['notification:html']], array_map(static fn ($notification) => $notification['id'], $page['notifications']), 'the one with nothing to show is left out');
        self::assertSame(['id' => $this->ids['notification:unread'], 'title' => 'Nouvelle réponse', 'content' => 'Paul a répondu à La sortie du samedi.', 'url' => '/bbs/la-sortie-du-samedi', 'adminUrl' => null, 'importance' => $page['notifications'][0]['importance'], 'isRead' => false, 'sentAt' => '2026-04-10T18:05:00+00:00', 'group' => 'yesterday'], $page['notifications'][0]);
        self::assertSame(['Un message', ''], [$page['notifications'][2]['title'], $page['notifications'][2]['content']], 'the subject for no title; a text stripping to nothing is "", not null');
        self::assertSame([2, false, '/notifications', '/notifications/read', '/notifications/latest', '/notifications/delete', '/push/subscribe', '/push/unsubscribe', false, null], [$page['unread'], $page['unreadOnly'], $page['allUrl'], $page['readUrl'], $page['latestUrl'], $page['deleteUrl'], $page['subscribeUrl'], $page['unsubscribeUrl'], $page['push'], $page['pushKey']]);
        self::assertTrue($this->tokenValid($response, 'notifications', $page['token']));
        $this->assertStoredByNoCache($response);
        self::assertTrue($this->props('/notifications?unread=1', $member)['unreadOnly']);
    }

    private function assertStoredByNoCache(Response $response): void
    {
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        self::assertFalse($response->headers->hasCacheControlDirective('public'));
        self::assertContains('Cookie', $response->getVary());
    }

    /** Whether a token is the one the CSRF token manager accepts for that intent, in the session the answer was made in. */
    private function tokenValid(Response $response, string $intent, string $token): bool
    {
        $request = Request::create('https://localhost/');
        $request->cookies->set($this->sessionName, $this->sessionId);
        $session = static::getContainer()->get('session.factory')->createSession();
        $session->setId($this->sessionId);
        $session->start();
        $request->setSession($session);
        static::getContainer()->get('request_stack')->push($request);
        try {
            return static::getContainer()->get('security.csrf.token_manager')->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken($intent, $token));
        } finally {
            static::getContainer()->get('request_stack')->pop();
        }
    }

    private string $sessionName = '';
    private string $sessionId = '';

    /**
     * A page's props, as a front end asks them.
     *
     * @return array<string, mixed>
     */
    private function props(string $path, ?object $user = null, ?Response &$response = null): array
    {
        $protocol = class_exists('Omnibridge\\React\\Protocol');
        $server = $protocol ? ['HTTP_X_OMNIBRIDGE' => 'true'] : ['HTTP_ACCEPT' => 'application/json'];
        self::ensureKernelShutdown();
        self::bootKernel();
        $request = Request::create('https://localhost'.$path, 'GET', [], [], [], $server);
        if ($user) {
            $session = static::getContainer()->get('session.factory')->createSession();
            $user = $this->em()->find($user::class, $user->getId());
            $session->set('_security_main', serialize(new UsernamePasswordToken($user, 'main', $user->getRoles())));
            $session->save();
            $request->cookies->set($this->sessionName = $session->getName(), $this->sessionId = $session->getId());
        }
        $response = static::$kernel->handle($request);
        self::assertSame(200, $response->getStatusCode(), $path.': '.substr((string) $response->getContent(), 0, 3000));
        self::assertStringContainsString('json', (string) $response->headers->get('Content-Type'));
        $data = json_decode((string) $response->getContent(), true);

        return $protocol ? $data['props'] : $data;
    }

    /** The values a page draws anew at each request, set aside; the records' ids by their names (another suite may have numbered before). */
    private function normalize(string $html): string
    {
        $html = preg_replace('~(name="_token" value=")[^"]*(")~', '$1*$2', $html);
        $html = preg_replace('~(data-csrf=")[^"]*(")~', '$1*$2', $html);
        $html = preg_replace('~([?&]_csrf_token=)[^"&]*~', '$1*', $html);
        // Each kind of record by its names: the users in their profile's address and number, the sessions in theirs, the notifications in their items.
        $names = fn (string $kind): array => array_flip(array_filter($this->ids, static fn ($id, $name) => str_starts_with($name, $kind), \ARRAY_FILTER_USE_BOTH));
        foreach (['~(/profile/|Member #)(\d+)~' => array_flip(array_intersect_key($this->ids, ['member' => 0, 'admin' => 0])), '~(/settings/sessions/)(\d+)~' => $names('session:'), '~(data-id=")(\d+)~' => $names('notification:')] as $pattern => $kind) {
            $html = preg_replace_callback($pattern, static fn ($m) => $m[1].(isset($kind[(int) $m[2]]) ? '{'.$kind[(int) $m[2]].'}' : $m[2]), $html);
        }

        return $html;
    }

    private function page(string $path, ?object $user = null, string $method = 'GET', int $status = 200): string
    {
        self::ensureKernelShutdown();
        self::bootKernel();
        $request = Request::create('https://localhost'.$path, $method);
        if ($user) {
            $session = static::getContainer()->get('session.factory')->createSession();
            $user = $this->em()->find($user::class, $user->getId());
            $session->set('_security_main', serialize(new UsernamePasswordToken($user, 'main', $user->getRoles())));
            $session->save();
            $request->cookies->set($session->getName(), $session->getId());
        }
        $response = static::$kernel->handle($request);
        self::assertSame($status, $response->getStatusCode(), $path.': '.substr((string) $response->getContent(), 0, 3000));

        return (string) $response->getContent();
    }

    /** What lesringostars' own controller does: the core's profile template, rendered with the user alone. */
    private function hostProfile(): string
    {
        self::ensureKernelShutdown();
        self::bootKernel();
        $request = Request::create('https://localhost/profile/of/someone');
        $request->setSession(new Session(new MockArraySessionStorage()));
        static::getContainer()->get('request_stack')->push($request);
        $user = $this->em()->find('App\\Entity\\User', $this->ids['member']);

        return static::getContainer()->get('twig')->render('@Base/client/user/profile_show.html.twig', ['user' => $user]);
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get('doctrine')->getManager();
    }

    private function user(string $name): object
    {
        return $this->em()->find('App\\Entity\\User', $this->ids[$name]);
    }

    private function seed(): void
    {
        $em = $this->em();
        $member = $this->account('Anne Membre', ['ROLE_USER']);
        $admin = $this->account('Paul Admin', ['ROLE_ADMIN']);
        $member->setEmailAuthEnabled(true);
        $admin->setTotpSecret('JBSWY3DPEHPK3PXP');
        $admin->setBackupCodes(['AAA111-BBB222', 'CCC333-DDD444', 'EEE555-FFF666']);
        $em->flush();

        // Three topics of the member's, two published and titled (the profile lists them, newest first), one a draft.
        $topic = self::TOPIC;
        $category = new \Base\Forum\Entity\Category('Le club');
        if (method_exists($category, 'setSlug')) {
            $category->setSlug('le-club');
        }
        $em->persist($category);
        $em->flush();
        $this->created[] = [$category::class, $category->getId()];
        $topics = [];
        foreach ([['La sortie du samedi', 'la-sortie-du-samedi', '2026-04-03 09:30:00'], ['Le club de lecture', 'le-club-de-lecture', '2026-04-08 18:00:00'], ['Un brouillon', 'un-brouillon', null]] as $i => [$title, $slug, $at]) {
            $thread = new $topic($member, $category, $title);
            $thread->setSlug($slug);
            if (null === $at) {
                $thread->setState(\Base\Enum\ThreadState::DRAFT);
            } else {
                $thread->setPublishedAt(new \DateTime($at));
            }
            $em->persist($thread);
            $topics[$slug] = $thread;
        }
        $em->flush();
        foreach ($topics as $slug => $thread) {
            $this->created[] = [$topic, $thread->getId()];
            $this->ids['topic:'.$slug] = $thread->getId();
        }

        // The member's sessions: one open, one signed out.
        $sessions = [];
        foreach ([['open', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15', '192.0.2.10', '2026-04-10 08:15:00', false], ['closed', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36', '198.51.100.7', '2026-04-02 21:40:00', true]] as [$name, $agent, $ip, $at, $closed]) {
            $connection = new Connection('uniqid-'.$name, $member);
            $connection->setAgent($agent);
            $connection->addIp($ip);
            $connection->markAsSucceeded();
            if ($closed) {
                $connection->markAsLogout();
            }
            $em->persist($connection);
            $sessions[$name] = [$connection, $at];
        }
        $em->flush();
        foreach ($sessions as $name => [$connection, $at]) {
            $this->created[] = [Connection::class, $connection->getId()];
            $this->ids['session:'.$name] = $connection->getId();
        }

        // The member's notifications: unread with an address, read, unread with HTML in its text; one with nothing to show.
        $notifications = [];
        foreach ([
            ['unread', 'Nouvelle réponse', '<p>Paul a répondu à <b>La sortie du samedi</b>.</p>', '/bbs/la-sortie-du-samedi', false, '2026-04-10 18:05:00'],
            ['read', 'Bienvenue', 'Votre compte est prêt.', null, true, '2026-04-09 07:00:00'],
            ['html', '', '<br>', null, false, '2026-03-30 12:00:00'],
            ['empty', '', '', null, false, '2026-04-11 12:00:00'],
        ] as [$name, $title, $content, $url, $read, $at]) {
            $notification = new Notification("");
            $notification->setUser($member);
            $notification->setTitle($title);
            $notification->setContent($content);
            if ('html' === $name) {
                $notification->setSubject('Un message');
            }
            $notification->setUrl($url);
            $notification->setIsRead($read);
            $em->persist($notification);
            $notifications[$name] = [$notification, $at];
        }
        $em->flush();
        foreach ($notifications as $name => [$notification, $at]) {
            $this->created[] = [Notification::class, $notification->getId()];
            $this->ids['notification:'.$name] = $notification->getId();
        }

        // Every moment fixed, after the flushes that write them. Active until 2100: no request pokes the accounts
        // (SecuritySubscriber writes "now" over an inactive one's activeAt and updatedAt), both shown online.
        $this->fix('App\\Entity\\User', $member->getId(), ['createdAt' => '2025-11-02 10:20:00', 'updatedAt' => '2026-04-10 19:00:00', 'activeAt' => '2100-01-01 00:00:00']);
        $this->fix('App\\Entity\\User', $admin->getId(), ['createdAt' => '2025-09-14 08:00:00', 'updatedAt' => '2026-04-11 09:00:00', 'activeAt' => '2100-01-01 00:00:00']);
        foreach ($topics as $thread) {
            $this->fix($topic, $thread->getId(), ['createdAt' => '2026-03-01 10:00:00', 'updatedAt' => '2026-04-12 10:00:00']);
        }
        foreach ($sessions as [$connection, $at]) {
            $this->fix(Connection::class, $connection->getId(), ['createdAt' => '2026-03-01 10:00:00', 'updatedAt' => $at]);
        }
        foreach ($notifications as [$notification, $at]) {
            $this->fix(Notification::class, $notification->getId(), ['sentAt' => $at]);
        }
        $em->clear();
    }

    /** @param array<string, ?string> $values a field's value by its name, in its declaring class's table */
    private function fix(string $class, int $id, array $values): void
    {
        $em = $this->em();
        $metadata = $em->getClassMetadata($class);
        foreach ($values as $field => $value) {
            $declared = $metadata->getFieldMapping($field)->declared ?? $metadata->name;
            $table = $em->getClassMetadata($declared);
            $em->getConnection()->update($table->getTableName(), [$table->getColumnName($field) => $value], [$table->getSingleIdentifierColumnName() => $id]);
        }
    }

    /** @param list<string> $roles */
    private function account(string $name, array $roles): object
    {
        $class = 'App\\Entity\\User';
        $user = new $class();
        $handle = strtolower(str_replace(' ', '.', $name));
        $user->setUsername($handle);
        $user->setEmail($handle.'@example.org');
        $user->setPlainPassword('test-'.$handle.'-password');
        $user->setRoles($roles);
        $this->em()->persist($user);
        $this->em()->flush();
        $this->created[] = [$class, $user->getId()];
        $this->ids[explode(' ', strtolower($name))[1] === 'admin' ? 'admin' : 'member'] = $user->getId();

        return $user;
    }
}
