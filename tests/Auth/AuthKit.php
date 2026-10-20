<?php

namespace Tests\Base\Auth;

use Base\Auth\Listener\TrackConnections;
use Base\Auth\Listener\UpdateAccount;
use Base\Auth\Listener\WelcomeBack;
use Base\Auth\Policy;
use Base\Auth\PolicyLinking;
use Base\Auth\UserAccounts;
use Base\Auth\UserBackupCodes;
use Base\Auth\UserEmailCodes;
use Base\Auth\UserIdentities;
use Base\Auth\UserLinkTokens;
use Base\Auth\UserPasskeys;
use Base\Auth\UserPasswords;
use Base\Auth\UserTotpSecrets;
use Base\Auth\UserTrustedVersion;
use Base\Service\SecurityPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Omniauth\ArrayState;
use Omniauth\BackupCode\BackupCodeMethod;
use Omniauth\Context;
use Omniauth\EmailCode\EmailCodeMethod;
use Omniauth\EnrolledSecondFactors;
use Omniauth\Link\LinkMethod;
use Omniauth\Method\Methods;
use Omniauth\OAuth\NoSecondFactorLinking;
use Omniauth\OAuth\OAuthMethod;
use Omniauth\OAuth\Provider\Providers;
use Omniauth\OAuth\Testing\FakeProvider;
use Omniauth\Omniauth;
use Omniauth\Passkey\PasskeyMethod;
use Omniauth\Passkey\RelyingParty;
use Omniauth\Password\PasswordMethod;
use Omniauth\Testing\FixedClock;
use Omniauth\Testing\RecordingSender;
use Omniauth\Totp\CacheUsedSteps;
use Omniauth\Totp\TotpMethod;
use Omniauth\TrustedDevice\TrustedDevices;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;

/**
 * omniauth mounted on omnibase's stores (Base\Auth\) and the host's own
 * database, every method of the four packages: what a host does at AU6/AU8,
 * by hand. The clock is fixed for the codes; the throttle is left out (the
 * conductor's own tests prove it), the session is an array.
 */
final class AuthKit
{
    public const SECRET = 'a-kernel-secret-of-thirty-two-bytes-or-more';
    public const ORIGIN = 'https://localhost';

    public readonly FixedClock $clock;
    public readonly RecordingSender $sender;
    public readonly UserAccounts $accounts;
    public readonly UserPasswords $passwords;
    public readonly UserLinkTokens $links;
    public readonly UserTotpSecrets $totpSecrets;
    public readonly UserEmailCodes $emailCodes;
    public readonly UserBackupCodes $backupCodes;
    public readonly UserTrustedVersion $trustedVersion;
    public readonly UserPasskeys $passkeys;
    public readonly UserIdentities $identities;
    public readonly Methods $methods;
    public readonly TotpMethod $totp;
    public readonly FakeProvider $google;
    public readonly Policy $policy;
    public readonly EventDispatcher $events;
    public readonly Omniauth $omniauth;
    /** @var array<string, mixed> */
    public array $session = [];
    /** @var list<object> */
    public array $dispatched = [];

    public function __construct(EntityManagerInterface $entityManager, SecurityPolicy $security, string $now = 'now')
    {
        $this->clock = new FixedClock((new \DateTimeImmutable($now))->format('Y-m-d H:i:s').' UTC');
        $this->sender = new RecordingSender();
        $this->accounts = new UserAccounts($entityManager);
        $this->passwords = new UserPasswords($entityManager);
        $this->links = new UserLinkTokens($entityManager);
        $this->totpSecrets = new UserTotpSecrets($entityManager);
        $this->emailCodes = new UserEmailCodes($entityManager);
        $this->backupCodes = new UserBackupCodes($entityManager);
        $this->trustedVersion = new UserTrustedVersion($entityManager);
        $this->passkeys = new UserPasskeys($entityManager);
        $this->identities = new UserIdentities($entityManager);

        $this->methods = new Methods([
            new PasswordMethod($this->accounts, $this->passwords, new NativePasswordHasher(cost: 4)),
            new LinkMethod($this->accounts, $this->links, $this->sender, clock: $this->clock),
            new EmailCodeMethod($this->accounts, $this->emailCodes, $this->sender, self::SECRET, clock: $this->clock),
            new BackupCodeMethod($this->backupCodes),
            new TrustedDevices($this->accounts, $this->trustedVersion, self::SECRET, clock: $this->clock),
            $this->totp = new TotpMethod($this->accounts, $this->totpSecrets, new CacheUsedSteps(new ArrayAdapter()), clock: $this->clock),
            new PasskeyMethod($this->accounts, $this->passkeys, new RelyingParty('localhost', 'omnibase')),
        ]);
        $this->policy = new Policy($security, new EnrolledSecondFactors($this->methods));
        $this->google = new FakeProvider('google');
        $this->methods->add(new OAuthMethod($this->accounts, $this->identities, new Providers($this->google), new PolicyLinking($this->policy, new NoSecondFactorLinking($this->methods)), $this->clock));

        $this->events = new EventDispatcher();
        foreach (Policy::events() as $event => $method) {
            $this->events->addListener($event, [$this->policy, $method]);
        }
        foreach ([new UpdateAccount($entityManager), new WelcomeBack($entityManager)] as $listener) {
            foreach ($listener::events() as $event => $method) {
                $this->events->addListener($event, [$listener, $method]);
            }
        }
        foreach ([\Omniauth\Event\SignedIn::class, \Omniauth\Event\SignInFailed::class, \Omniauth\Event\AccountRefused::class, \Omniauth\Event\SecondFactorRequired::class, \Omniauth\Event\Revoking::class] as $event) {
            $this->events->addListener($event, function (object $e): void {
                $this->dispatched[] = $e;
            }, -100);
        }

        $this->omniauth = new Omniauth($this->accounts, $this->methods, new ArrayState($this->session), $this->policy, events: $this->events, clock: $this->clock);
    }

    /**
     * The packages these tests sign in with: the core only suggests them (the
     * harness's image installs them; an application's suite reading this
     * package in its vendor/ may not).
     *
     * @return string|null why the tests cannot run, or null
     */
    public static function missing(): ?string
    {
        foreach (['omniscient/omniauth' => \Omniauth\Omniauth::class, 'omniauth/passkey' => \Omniauth\Passkey\PasskeyMethod::class, 'omniauth/totp' => \Omniauth\Totp\TotpMethod::class, 'omniauth/oauth' => \Omniauth\OAuth\OAuthMethod::class] as $package => $class) {
            if (!class_exists($class)) {
                return $package.' is not installed (the core suggests it).';
            }
        }

        return null;
    }

    public static function context(?string $trustedDevice = null): Context
    {
        return new Context(self::ORIGIN, 'localhost', '203.0.113.9', 'PHPUnit', $trustedDevice);
    }

    /** Signs in through the fake Google: the trip out, the person's consent, the return. */
    public function google(array $person): \Omniauth\Result
    {
        $challenge = $this->omniauth->begin('oauth', self::context(), ['provider' => 'google']);

        return $this->omniauth->verify('oauth', self::context(), $this->google->consent($challenge->payload, $person));
    }

    /** @param class-string $class */
    public function last(string $class): ?object
    {
        foreach (array_reverse($this->dispatched) as $event) {
            if ($event instanceof $class) {
                return $event;
            }
        }

        return null;
    }
}
