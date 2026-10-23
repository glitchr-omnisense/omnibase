<?php

namespace Tests\Base\Auth;

use Base\Auth\Listener\TrackConnections;
use Base\Auth\Listener\WarnNewDevice;
use Base\Auth\NotifierSender;
use Base\Auth\UserAccounts;
use Base\Entity\User\Connection;
use Base\Entity\User\Notification;
use Base\Entity\User\Token;
use Base\Notifier\Mail\EmailComposer;
use Base\Notifier\Mail\SignInMails;
use Base\Notifier\Notifier;
use Base\Notifier\Recipient\Recipient;
use Base\Repository\User\ConnectionRepository;
use Base\Security\ConnectionTracker;
use Base\Security\SignInCaptcha;
use Base\Service\FormGuard;
use Base\Service\ReadSettings;
use Base\Service\SecurityPolicy;
use Omniauth\ArrayState;
use Omniauth\Context;
use Omniauth\Factor;
use Omniauth\Message;
use Omniauth\Omniauth;
use Omnibridge\Contract\Translations;
use Omnibridge\Contract\Urls;
use Omnishield\Registry;
use Omnishield\Testing\FixedGateway;
use Omnishield\Testing\FixedGatewayFactory;
use Symfony\Bridge\Twig\Mime\BodyRenderer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Tests\Base\Http\HttpTestTrait;

/**
 * The effects of a sign-in written without Symfony (plan "omniauth", step
 * AU8b; R25 and R26 of the master plan's gaps), as a host without its
 * RequestStack, its Notifier or its forms has them (omnibridge/laravel):
 *
 * - the browser's connection by ConnectionTracker on the host's own browser
 *   identifier and host - the row UserTracker writes;
 * - the captcha after failures, its widget printed without omnishield's
 *   Symfony form field (SignInCaptcha);
 * - the e-mails of signing in by SignInMails - each the very e-mail the
 *   Notifier writes for it (subject, sender, recipient, HTML, text);
 * - the second-factor rule on the stored settings and a host's role
 *   hierarchy (ReadSettings, SecurityPolicy's $reachableRoles).
 */
final class HostlessEffectsTest extends KernelTestCase
{
    use HttpTestTrait;
    use KeepsServerGlobals;

    /** @var list<Email> */
    private array $sent = [];

    protected function setUp(): void
    {
        if (null !== $missing = AuthKit::missing()) {
            self::markTestSkipped($missing);
        }
        $this->keepServerGlobals();
        $this->bootHost();
    }

    protected function tearDown(): void
    {
        if (!static::$booted) {
            parent::tearDown();

            return;
        }
        $this->removeUsers();
        $this->restoreServerGlobals();
        parent::tearDown();
    }

    public function testAConnectionIsTheRowUserTrackerWritesWithTheHostsOwnBrowserAndHost(): void
    {
        $user = $this->createUser();
        $_SERVER['REMOTE_ADDR'] = '203.0.113.21';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux x86_64) Firefox/131.0';
        $connections = $this->entityManager()->getRepository(Connection::class);
        \assert($connections instanceof ConnectionRepository);
        $browser = 'browser-of-another-host';
        $tracker = new ConnectionTracker($this->entityManager(), $connections, static fn (): string => $browser, static fn (): string => 'twin.example.org');

        try {
            $events = new EventDispatcher();
            $track = new TrackConnections($tracker, $this->entityManager(), new UserAccounts($this->entityManager()));
            foreach (TrackConnections::events() as $event => $method) {
                $events->addListener($event, [$track, $method]);
            }
            $kit = new AuthKit($this->entityManager(), new SecurityPolicy(new ReadSettings(static fn (string $path) => null)));
            $omniauth = new Omniauth($kit->accounts, $kit->methods, new ArrayState(), events: $events);
            self::assertTrue($omniauth->verify('password', new Context('https://twin.example.org', 'twin.example.org', '203.0.113.21'), ['identifier' => $user->getEmail(), 'password' => 'test-'.$user->getUsername()])->isAuthenticated());

            $connection = $tracker->getCurrentConnection($user, false);
            self::assertNotNull($connection, 'the sign-in made its browser\'s connection');
            self::assertSame($browser, $connection->getUniqid());
            self::assertSame(['203.0.113.21'], $connection->getIpList());
            self::assertSame('Mozilla/5.0 (X11; Linux x86_64) Firefox/131.0', $connection->getAgent());
            self::assertSame(['twin.example.org'], $connection->getHostnames());
            self::assertSame(\Base\Enum\ConnectionState::SUCCEEDED, $connection->getState());

            $_SERVER['REMOTE_ADDR'] = '203.0.113.22';
            $tracker->updateConnection($user);
            self::assertSame(['203.0.113.21', '203.0.113.22'], $tracker->getCurrentConnection($user, false)?->getIpList(), 'a later request of the same browser adds its address');
            self::assertSame(1, $connections->countFor($user));
        } finally {
            $this->entityManager()->getConnection()->executeStatement('DELETE FROM userConnection WHERE user_id = ?', [$user->getId()]);
        }
    }

    public function testTheCaptchaWidgetIsPrintedWithoutTheSymfonyForm(): void
    {
        if (!class_exists(Registry::class)) {
            self::markTestSkipped('glitchr/omnishield is not installed.');
        }
        $captcha = new SignInCaptcha(new FormGuard('secret', ['sign_in_after' => 2], new Registry([new FixedGatewayFactory()], ['forms' => ['factory' => 'fixed']]), null, 'forms'), new ArrayAdapter());

        self::assertSame('', $captcha->widgetHtml('203.0.113.30'), 'no failure, no captcha');
        $captcha->failed('203.0.113.30');
        $captcha->failed('203.0.113.30');
        self::assertSame('forms', $captcha->requiredFor('203.0.113.30'));
        $html = $captcha->widgetHtml('203.0.113.30');
        self::assertStringContainsString(FixedGateway::FIELD, $html, 'the widget, whose field the answer is posted in');
        self::assertSame('', $captcha->widgetHtml('198.51.100.30'), 'not for another address');
        self::assertTrue($captcha->passes('forms', FixedGateway::TOKEN, '203.0.113.30'));
        $captcha->forget('203.0.113.30');
        self::assertNull($captcha->requiredFor('203.0.113.30'));
    }

    /**
     * Each e-mail of signing in written by SignInMails is the e-mail the
     * Notifier writes (asEmailMessage(), rendered as Symfony's mailer renders
     * it): the same subject, sender, recipient, priority, HTML and text, the
     * random names of their embedded pictures aside.
     */
    public function testTheEmailsOfSigningInAreTheNotifiers(): void
    {
        $user = $this->createUser();
        $container = static::getContainer();
        $notifier = $container->get(Notifier::class);
        $twig = $container->get('twig');
        $mails = $this->mails();
        $until = new \DateTimeImmutable('+15 minutes');
        $token = new Token(UserAccounts::WELCOME_BACK, 3600);
        $token->setUser($user);
        $connection = new Connection('a-browser', $user);
        $connection->addIp('203.0.113.40');
        $connection->setAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) Safari/605.1.15');

        $newDevice = new Notification('newDevice.subject', [$user]);
        $newDevice->setUser($user);
        $newDevice->setHtmlTemplate(SignInMails::NEW_DEVICE_TEMPLATE, SignInMails::newDeviceParameters($user, $connection));

        $cases = [
            'link' => [$notifier->loginLink($user, 'the-link-token', $until), fn () => $mails->loginLink($user, 'the-link-token', $until)],
            'code' => [$notifier->emailCode($user, '123456', $until), fn () => $mails->emailCode($user, '123456', $until)],
            'welcome back' => [$notifier->userWelcomeBack($user, $token), fn () => $mails->welcomeBack($user, $token)],
            'new device' => [$newDevice, fn () => $mails->newDevice($user, $connection)],
        ];
        foreach ($cases as $name => [$notification, $send]) {
            $symfony = $notification->asEmailMessage($user->getRecipient())->getMessage();
            (new BodyRenderer($twig))->render($symfony);
            $this->sent = [];
            $send();
            self::assertCount(1, $this->sent, $name);
            $hostless = $this->sent[0];

            self::assertSame($this->summary($symfony), $this->summary($hostless), $name);
            self::assertNotSame('', (string) $hostless->getSubject(), $name.': a subject');
        }
    }

    public function testNotifierSenderSendsThroughTheMailsWhereThereIsNoNotifier(): void
    {
        $user = $this->createUser();
        $sender = new NotifierSender($this->mails());
        $until = new \DateTimeImmutable('+10 minutes');

        $sender->send($user, new Message(Factor::EmailCode, '654321', $until));
        $sender->send($user, new Message(Factor::Link, 'a-link-token', $until));
        self::assertCount(2, $this->sent);
        self::assertStringContainsString('654321', (string) $this->sent[0]->getHtmlBody());
        self::assertStringContainsString('a-link-token', (string) $this->sent[1]->getHtmlBody());
    }

    public function testANewDeviceIsWarnedOfByTheMailsAndTheRuleReadsTheStoredSettings(): void
    {
        $user = $this->createUser();
        $_SERVER['REMOTE_ADDR'] = '203.0.113.50';
        $connections = $this->entityManager()->getRepository(Connection::class);
        \assert($connections instanceof ConnectionRepository);
        $browser = 'first-browser';
        $tracker = new ConnectionTracker($this->entityManager(), $connections, static function () use (&$browser): string {
            return $browser;
        }, static fn (): string => 'twin.example.org');
        $settings = [SecurityPolicy::NEW_DEVICE_EMAIL => true, SecurityPolicy::NEW_DEVICE_PROMPT => '1'];
        $policy = new SecurityPolicy(new ReadSettings(static fn (string $path) => $settings[$path] ?? null));
        $state = [];

        try {
            $events = new EventDispatcher();
            $track = new TrackConnections($tracker, $this->entityManager(), new UserAccounts($this->entityManager()));
            $warn = new WarnNewDevice($policy, $tracker, $connections, new ArrayState($state), static fn (): bool => false, $this->mails());
            foreach ([$track, $warn] as $listener) {
                foreach ($listener::events() as $event => $method) {
                    $events->addListener($event, [$listener, $method]);
                }
            }
            $kit = new AuthKit($this->entityManager(), $policy);
            $omniauth = new Omniauth($kit->accounts, $kit->methods, new ArrayState(), events: $events);
            $context = new Context('https://twin.example.org', 'twin.example.org', '203.0.113.50');
            $right = ['identifier' => $user->getEmail(), 'password' => 'test-'.$user->getUsername()];

            self::assertTrue($omniauth->verify('password', $context, $right)->isAuthenticated());
            self::assertSame([], $this->sent, 'the account\'s very first browser: nothing to warn of');

            $browser = 'second-browser';
            self::assertTrue($omniauth->verify('password', $context, $right)->isAuthenticated());
            self::assertCount(1, $this->sent, 'a new browser: the e-mail');
            $mail = current($this->sent);
            self::assertInstanceOf(Email::class, $mail);
            self::assertStringContainsString('203.0.113.50', (string) $mail->getHtmlBody());
            self::assertTrue($state[SecurityPolicy::SESSION_NEW_DEVICE_PROMPT] ?? false, 'and the offer of a second factor');
        } finally {
            $this->entityManager()->getConnection()->executeStatement('DELETE FROM userConnection WHERE user_id = ?', [$user->getId()]);
        }
    }

    public function testTheRuleReadsAHostsRoleHierarchy(): void
    {
        $user = $this->createUser(['ROLE_SUPERADMIN']);
        $settings = new ReadSettings(static fn (string $path) => null);
        $hierarchy = static fn (array $roles): array => \in_array('ROLE_SUPERADMIN', $roles, true) ? [...$roles, 'ROLE_ADMIN', 'ROLE_USER'] : $roles;

        self::assertFalse((new SecurityPolicy($settings, null, ['ROLE_ADMIN']))->isTwoFactorRequiredByRole($user), 'without a hierarchy, the account\'s own roles');
        self::assertTrue((new SecurityPolicy($settings, null, ['ROLE_ADMIN'], true, null, null, $hierarchy))->isTwoFactorRequiredByRole($user), 'the roles its own reach');
        $this->expectException(\LogicException::class);
        $settings->set(SecurityPolicy::TWO_FACTOR, false);
    }

    /** `app` for the e-mail templates where the host has no AppVariable: the settings SiteVariable and EmailVariable read, the same way. */
    public function testTheEmailsAppVariablesReadTheSettingsAsSymfonysDo(): void
    {
        $year = (int) date('Y');
        $settings = ['base.settings.title' => 'Les Ringo Stars', 'base.settings.logo' => '/logo.png', 'base.settings.launchdate' => ['date' => ($year - 3).'-05-01 00:00:00.000000', 'timezone' => 'UTC']];
        $app = new \Base\Notifier\Mail\MailVariables(new ReadSettings(static fn (string $path) => $settings[$path] ?? null), static fn (): string => 'example.org');

        self::assertSame('example.org', $app->site->domain());
        self::assertSame('/logo.png', $app->site->logo());
        self::assertSame('Les Ringo Stars', $app->email->title(), 'the e-mails\' own title, else the site\'s');
        self::assertSame('/logo.png', $app->email->logo());
        self::assertSame(($year - 3).'-'.$year, $app->email->age(), 'Launcher::since()');
        self::assertSame((string) $year, (new \Base\Notifier\Mail\MailVariables(new ReadSettings(static fn () => null), static fn (): ?string => null))->email->age(), 'no launch date: this year');
    }

    /** SignInMails as a host without the Notifier builds it, on this kernel's Twig, words and addresses: e-mails kept in $this->sent. */
    private function mails(): SignInMails
    {
        $container = static::getContainer();
        $notifier = $container->get(Notifier::class);
        $mailer = new class($this->sent) implements MailerInterface {
            /** @param list<Email> $sent */
            public function __construct(private array &$sent) // @phpstan-ignore property.onlyWritten (the test reads it, by reference)
            {
            }

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                \assert($message instanceof Email);
                $this->sent[] = $message;
            }
        };
        $composer = new EmailComposer(
            $container->get('twig'),
            static fn (): string => $notifier->getTechnicalRecipient()->getEmail(),
            static fn (string $to): bool => $notifier->isTest(new Recipient($to)),
            static fn (string $id): string => $notifier->getTranslator()->trans($id),
        );

        // The addresses as the Notifier's router writes them in this kernel (no request: a path); a host's Urls
        // gives them whole, as the router does on a request.
        $router = $container->get(\Base\Routing\AdvancedRouterInterface::class);
        $urls = new class($router, $container->get(Urls::class)) implements Urls {
            public function __construct(private readonly object $router, private readonly Urls $urls)
            {
            }

            public function path(string $name, array $parameters = []): string
            {
                return $this->urls->path($name, $parameters);
            }

            public function url(string $name, array $parameters = []): string
            {
                return $this->router->getUrl($name, $parameters);
            }

            public function asset(string $path): string
            {
                return $this->urls->asset($path);
            }
        };

        return new SignInMails($composer, $mailer, $container->get(Translations::class), $urls);
    }

    /** @return array<string, mixed> */
    private function summary(Email $email): array
    {
        // The embedded pictures' random names, and the seconds that passed between the two e-mails ("expires in 14 minutes 59 seconds").
        $cid = static fn (?string $s): string => (string) preg_replace(['/cid:[^"\'\s)]+/', '/\d+ (?=\S*\s*(seconds?|secondes?|minutes?)\b)/u'], ['cid:*', 'N '], (string) $s);

        $email->getPreparedHeaders(); // the Notifier's NotificationEmail sets its priority there

        return [
            'subject' => $email->getSubject(),
            'from' => array_map(static fn ($a) => $a->toString(), $email->getFrom()),
            'to' => array_map(static fn ($a) => $a->toString(), $email->getTo()),
            'priority' => $email->getPriority(),
            'html' => $cid($email->getHtmlBody()),
            'text' => $cid($email->getTextBody()),
            'parts' => \count($email->getAttachments()),
        ];
    }
}
