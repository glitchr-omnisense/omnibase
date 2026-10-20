<?php

namespace Tests\Base\Auth;

use Base\Auth\Listener\GuardSignIn;
use Base\Auth\Listener\TrackConnections;
use Base\Auth\Listener\WarnNewDevice;
use Base\Entity\User\Connection;
use Base\Repository\User\ConnectionRepository;
use Base\Security\SignInGuard;
use Base\Security\UserTracker;
use Base\Service\FormGuard;
use Base\Service\SecurityPolicy;
use Base\Service\SettingBagInterface;
use Omniauth\ArrayState;
use Omniauth\Context;
use Omniauth\Factor;
use Omniauth\Failure;
use Omniauth\Method\Methods;
use Omniauth\Omniauth;
use Omniauth\Password\PasswordMethod;
use Omniauth\Testing\InMemoryStore;
use Omniauth\Testing\TestAccount;
use Omnishield\Registry;
use Omnishield\Testing\FixedGateway;
use Omnishield\Testing\FixedGatewayFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;
use Tests\Base\Http\HttpTestTrait;

/**
 * omnibase's listeners on omniauth's events (plan "omniauth", AU4): the
 * sign-in's captcha after failures from an address (SignInGuard), the
 * browsers' connections (UserTracker) and the e-mail of a new device - each
 * what its Symfony subscriber does, through the conductor.
 */
final class ListenersTest extends KernelTestCase
{
    use HttpTestTrait;
    use KeepsServerGlobals;

    protected function setUp(): void
    {
        $this->keepServerGlobals();
        $this->bootHost();
    }

    protected function tearDown(): void
    {
        $this->removeUsers();
        $this->restoreServerGlobals();
        parent::tearDown();
    }

    public function testTheCaptchaAfterFailuresFromAnAddressAndOnlyThere(): void
    {
        if (!class_exists(Registry::class)) {
            self::markTestSkipped('glitchr/omnishield is not installed.');
        }
        $guard = new SignInGuard(new FormGuard('secret', ['sign_in_after' => 2], new Registry([new FixedGatewayFactory()], ['forms' => ['factory' => 'fixed']]), null, 'forms'), new ArrayAdapter());
        $posted = [];
        $listener = new GuardSignIn($guard, static function (string $field) use (&$posted): ?string {
            return $posted[$field] ?? null;
        });
        $hasher = new NativePasswordHasher(cost: 4);
        $store = new InMemoryStore(new TestAccount('1', 'anne', 'anne@example.org', $hasher->hash('right')));
        $events = new EventDispatcher();
        foreach (GuardSignIn::events() as $event => $method) {
            $events->addListener($event, [$listener, $method]);
        }
        $omniauth = new Omniauth($store, new Methods([new PasswordMethod($store, $store, $hasher)]), new ArrayState(), events: $events);
        $here = new Context('https://example.org', 'example.org', '203.0.113.5');
        $wrong = ['identifier' => 'anne', 'password' => 'wrong'];
        $right = ['identifier' => 'anne', 'password' => 'right'];

        $omniauth->verify('password', $here, $wrong);
        $omniauth->verify('password', $here, $wrong);
        self::assertSame('forms', $guard->requiredFor('203.0.113.5'), 'two failures: the captcha');
        self::assertNull($guard->requiredFor('198.51.100.9'), 'not for another address');

        $refused = $omniauth->verify('password', $here, $right);
        self::assertSame([Failure::Refused, GuardSignIn::REFUSED], [$refused->failure, $refused->reason], 'without its answer, refused before the password is checked');
        self::assertTrue($omniauth->verify('password', new Context('https://example.org', 'example.org', '203.0.113.5', firewall: 'rescue'), $right)->isAuthenticated(), 'not the rescue door');

        $posted = [FixedGateway::FIELD => FixedGateway::TOKEN];
        self::assertSame(FixedGateway::FIELD, $guard->field('forms'));
        self::assertTrue($omniauth->verify('password', $here, $right)->isAuthenticated(), 'with it');
        self::assertNull($guard->requiredFor('203.0.113.5'), 'a success forgets the count');
    }

    public function testASignInMarksItsBrowsersConnectionAndANewOneIsWarnedOf(): void
    {
        $user = $this->createUser();
        $requests = static::getContainer()->get(RequestStack::class);
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/login', 'POST', server: ['REMOTE_ADDR' => '203.0.113.7', 'HTTP_USER_AGENT' => 'PHPUnit']);
        $request->setSession($session);
        $requests->push($request);

        try {
            $tracker = static::getContainer()->get(UserTracker::class);
            $connections = $this->entityManager()->getRepository(Connection::class);
            \assert($connections instanceof ConnectionRepository);
            $bag = $this->createStub(SettingBagInterface::class);
            $bag->method('getScalar')->willReturnCallback(static fn ($path) => [SecurityPolicy::NEW_DEVICE_PROMPT => '1'][$path] ?? null);
            $state = [];
            $events = new EventDispatcher();
            $track = new TrackConnections($tracker, $this->entityManager(), new \Base\Auth\UserAccounts($this->entityManager()));
            $warn = new WarnNewDevice(new SecurityPolicy($bag), $tracker, $connections, new ArrayState($state), static fn (): bool => false);
            foreach ([$track, $warn] as $listener) {
                foreach ($listener::events() as $event => $method) {
                    $events->addListener($event, [$listener, $method]);
                }
            }

            $kit = new AuthKit($this->entityManager(), new SecurityPolicy($bag));
            $omniauth = new Omniauth($kit->accounts, $kit->methods, new ArrayState(), events: $events);
            $context = new Context('https://localhost', 'localhost', '203.0.113.7');

            $omniauth->verify('password', $context, ['identifier' => $user->getEmail(), 'password' => 'wrong']);
            $connection = $tracker->getCurrentConnection($user, false);
            self::assertNotNull($connection, 'a failure on a known account is noted on its browser\'s connection');
            self::assertSame(1, $connections->countFor($user));
            self::assertArrayNotHasKey(SecurityPolicy::SESSION_NEW_DEVICE_PROMPT, $state);

            self::assertTrue($omniauth->verify('password', $context, ['identifier' => $user->getEmail(), 'password' => 'test-'.$user->getUsername()])->isAuthenticated());
            self::assertArrayNotHasKey(SecurityPolicy::SESSION_NEW_DEVICE_PROMPT, $state, 'the account\'s very first browser: nothing to warn of');

            // Another browser (another PHPUSERID): a connection this sign-in made, the second of the account.
            $session->remove(UserTracker::PHPUSERID);
            self::assertTrue($omniauth->verify('password', $context, ['identifier' => $user->getEmail(), 'password' => 'test-'.$user->getUsername()])->isAuthenticated());
            self::assertSame(2, $connections->countFor($user));
            self::assertTrue($state[SecurityPolicy::SESSION_NEW_DEVICE_PROMPT] ?? false, 'the offer to set up a code, once, on the next page');
        } finally {
            $this->entityManager()->getConnection()->executeStatement('DELETE FROM userConnection WHERE user_id = ?', [$user->getId()]);
            $requests->pop();
        }
    }
}
