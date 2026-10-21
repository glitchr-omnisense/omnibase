<?php

namespace Tests\Base\Auth;

use Base\Auth\Policy;
use Base\Auth\UserAccounts;
use Base\Entity\User\Identity;
use Base\Entity\User\Passkey;
use Base\Entity\User\Token;
use Base\Service\SecurityPolicy;
use Base\Service\SettingBagInterface;
use Omniauth\Event\AccountRefused;
use Omniauth\Factor;
use Omniauth\Failure;
use Omniauth\Link\LinkMethod;
use Omniauth\Passkey\Testing\SoftwareAuthenticator;
use Omniauth\Status;
use Omniauth\Totp\Testing\AuthenticatorApp;
use Omniauth\Totp\TotpMethod;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Base\Http\HttpTestTrait;

/**
 * omniauth on omnibase's tables (plan "omniauth", step AU4): every method of
 * omniauth and its three packages signing accounts of the host's database in
 * through Base\Auth\'s stores, Base\Auth\Policy and the listeners - the
 * conductor mounted by hand (AuthKit), as a host will (AU6, AU8). Against the
 * harness's test database (a fresh SQLite one), the accounts removed after.
 */
final class OmniauthOnOmnibaseTest extends KernelTestCase
{
    use HttpTestTrait;
    use KeepsServerGlobals;

    private AuthKit $kit;

    protected function setUp(): void
    {
        if (null !== $missing = AuthKit::missing()) {
            self::markTestSkipped($missing);
        }
        $this->keepServerGlobals();
        $this->bootHost();
        $this->kit = new AuthKit($this->entityManager(), $this->security(), notifier: static::getContainer()->has('Base\\Notifier\\Notifier') ? static::getContainer()->get('Base\\Notifier\\Notifier') : null);
    }

    protected function tearDown(): void
    {
        if (null !== AuthKit::missing()) {
            parent::tearDown();

            return;
        }
        $connection = $this->entityManager()->getConnection();
        foreach ($this->createdUsers as $user) {
            $connection->executeStatement('DELETE FROM userPasskey WHERE user_id = ?', [$user->getId()]);
            $connection->executeStatement('DELETE FROM userIdentity WHERE user_id = ?', [$user->getId()]);
        }
        $this->entityManager()->clear();
        $this->removeUsers();
        unset($this->kit); // PHPUnit keeps every test case to the end of the run: not its entity manager
        $this->restoreServerGlobals();
        parent::tearDown();
    }

    /** @param array<string, mixed> $settings */
    private function security(array $requiredRoles = [], array $settings = []): SecurityPolicy
    {
        $bag = $this->createStub(SettingBagInterface::class);
        $bag->method('getScalar')->willReturnCallback(static fn ($path) => $settings[$path] ?? null);

        return new SecurityPolicy($bag, null, $requiredRoles);
    }

    private static function password(object $user): string
    {
        return 'test-'.$user->getUsername();
    }

    private function password_(object $user): \Omniauth\Result
    {
        return $this->kit->omniauth->verify('password', AuthKit::context(), ['identifier' => $user->getEmail(), 'password' => self::password($user)]);
    }

    public function testAPasswordAloneSignsInAndTheAccountIsBroughtUpToDate(): void
    {
        $user = $this->createUser();
        $user->kick();
        $this->entityManager()->flush();

        $result = $this->password_($user);

        self::assertTrue($result->isAuthenticated());
        self::assertSame($user->getId(), $result->account->getId());
        self::assertSame([Factor::Password], $result->passed);
        self::assertFalse($user->isKicked(), 'UpdateAccount lifted the kick, as LoginFormAuthenticator does');
        self::assertSame(Failure::BadCredentials, $this->kit->omniauth->verify('password', AuthKit::context(), ['identifier' => $user->getEmail(), 'password' => 'wrong'])->failure);
        self::assertSame(Failure::BadCredentials, $this->kit->omniauth->verify('password', AuthKit::context(), ['identifier' => 'nobody@example.org', 'password' => 'wrong'])->failure, 'one answer for both');
        self::assertTrue($this->kit->omniauth->verify('password', AuthKit::context(), ['identifier' => strtoupper($user->getEmail()), 'password' => self::password($user)])->isAuthenticated(), 'the address, whatever its case');
    }

    public function testAnAuthenticatorApplicationThenABackupCodeThenATrustedDevice(): void
    {
        $user = $this->createUser();
        $secret = TotpMethod::secret();
        $this->kit->totpSecrets->enable($user, $secret);
        $codes = $this->kit->omniauth->enrol(Factor::BackupCode, $user)->confirm(AuthKit::context())->secrets;
        $app = AuthenticatorApp::of($secret, $this->kit->clock);

        $first = $this->password_($user);
        self::assertSame(Status::SecondFactorRequired, $first->status);
        self::assertSame([Factor::Totp, Factor::BackupCode], $first->offered);
        $this->entityManager()->clear();

        $result = $this->kit->omniauth->verify('totp', AuthKit::context(), ['code' => $app->code(), 'trust' => true]);
        self::assertTrue($result->isAuthenticated());
        self::assertSame([Factor::Password, Factor::Totp], $result->passed);
        self::assertNotNull($result->trustDevice, 'the trusted_device cookie, scheb\'s format');

        // The same code again: refused (omniauth/totp, once only); a backup code serves, once.
        $this->password_($user);
        self::assertSame('replayed', $this->kit->omniauth->verify('totp', AuthKit::context(), ['code' => $app->code()])->reason);
        self::assertTrue($this->kit->omniauth->verify('backup-code', AuthKit::context(), ['code' => $codes[0]])->isAuthenticated());
        $this->entityManager()->clear();
        $reloaded = $this->kit->accounts->byHandle((string) $user->getId());
        self::assertCount(\count($codes) - 1, $reloaded->getBackupCodeHashes(), 'the code used is gone from user.backupCodes');

        // The trusted browser: no second factor; then every device falls back with the version.
        self::assertTrue($this->kit->omniauth->verify('password', AuthKit::context($result->trustDevice), ['identifier' => $user->getEmail(), 'password' => self::password($user)])->isAuthenticated());
        $this->kit->trustedVersion->revokeAll($reloaded);
        self::assertSame(1, (int) $this->entityManager()->getConnection()->fetchOne('SELECT trustedTokenVersion FROM user WHERE id = ?', [$user->getId()]));
        self::assertTrue($this->kit->omniauth->verify('password', AuthKit::context($result->trustDevice), ['identifier' => $user->getEmail(), 'password' => self::password($user)])->needsSecondFactor());
    }

    public function testACodeByEmailIsKeptHashedAndUsedOnce(): void
    {
        $user = $this->createUser();
        $this->kit->emailCodes->enable($user);

        self::assertSame([Factor::EmailCode], $this->password_($user)->offered);
        $this->kit->omniauth->begin('email-code', AuthKit::context());
        $code = $this->kit->sender->last()->secret;
        $kept = $this->entityManager()->getConnection()->fetchOne('SELECT emailAuthCode FROM user WHERE id = ?', [$user->getId()]);
        self::assertMatchesRegularExpression('~^\d{6}$~', $code);
        self::assertNotSame($code, $kept, 'QA5: the column holds the code\'s keyed hash, not the code');
        self::assertSame(64, \strlen((string) $kept));

        self::assertTrue($this->kit->omniauth->verify('email-code', AuthKit::context(), ['code' => $code])->isAuthenticated());
        self::assertNull($this->entityManager()->getConnection()->fetchOne('SELECT emailAuthCode FROM user WHERE id = ?', [$user->getId()]), 'forgotten once used');
    }

    public function testASignInLinkIsKeptHashedServesOnceAndTheLinksOfBeforeStillServe(): void
    {
        $user = $this->createUser();
        $this->kit->omniauth->begin('link', AuthKit::context(), ['identifier' => $user->getEmail()]);
        $message = $this->kit->sender->last();
        $row = $this->entityManager()->getConnection()->fetchAssociative('SELECT value, isLogginable, isRevoked, expireAt FROM userToken WHERE user_id = ? AND name = ?', [$user->getId(), 'login-token']);

        self::assertSame(LinkMethod::hash($message->secret), $row['value'], 'QA4: the SHA-256, never the link');
        self::assertSame([1, 0], [(int) $row['isLogginable'], (int) $row['isRevoked']]);
        self::assertSame($message->until->getTimestamp(), (new \DateTimeImmutable($row['expireAt'], new \DateTimeZone('UTC')))->getTimestamp(), '15 minutes, the moment omniauth gave');

        $result = $this->kit->omniauth->verify('link', AuthKit::context(), ['token' => $message->secret]);
        self::assertTrue($result->isAuthenticated());
        self::assertTrue($result->remember, 'the host\'s remember-me follows, as before');
        self::assertSame(0, (int) $this->entityManager()->getConnection()->fetchOne('SELECT COUNT(*) FROM userToken WHERE user_id = ? AND name = ?', [$user->getId(), 'login-token']), 'used up');
        self::assertSame(Failure::InvalidLink, $this->kit->omniauth->verify('link', AuthKit::context(), ['token' => $message->secret])->failure, 'once');

        // A link sent before the switch (its value in clear, 30 days): read as it is until its deadline.
        $plain = new Token('login-token', 30 * 24 * 3600, 0);
        $plain->markAsLogginable();
        $user = $this->entityManager()->find($user::class, $user->getId());
        $user->addToken($plain);
        $this->entityManager()->persist($plain);
        $this->entityManager()->flush();
        self::assertSame(Failure::InvalidLink, $this->kit->omniauth->verify('link', AuthKit::context(), ['token' => LinkMethod::hash($plain->getValue())])->failure);
        self::assertTrue($this->kit->omniauth->verify('link', AuthKit::context(), ['token' => $plain->getValue()])->isAuthenticated());
        self::assertSame(Failure::InvalidLink, $this->kit->omniauth->verify('link', AuthKit::context(), ['token' => $plain->getValue()])->failure, 'once too');

        // The column's text is no link: a hash presented as a link is refused.
        $this->kit->omniauth->begin('link', AuthKit::context(), ['identifier' => $user->getEmail()]);
        $hash = LinkMethod::hash($this->kit->sender->last()->secret);
        self::assertSame(Failure::InvalidLink, $this->kit->omniauth->verify('link', AuthKit::context(), ['token' => $hash])->failure);
        self::assertSame(1, (int) $this->entityManager()->getConnection()->fetchOne('SELECT COUNT(*) FROM userToken WHERE user_id = ? AND name = ?', [$user->getId(), 'login-token']), 'the link is still there');
    }

    public function testALinkPastItsMomentDoesNotSignIn(): void
    {
        $user = $this->createUser();
        $this->kit->links->issue($user, LinkMethod::hash('abc'), $this->kit->clock->now()->modify('+10 seconds'));

        self::assertNull($this->kit->links->consume(LinkMethod::hash('abc'), $this->kit->clock->now()->modify('+11 seconds')));
        self::assertSame($user->getId(), $this->kit->links->consume(LinkMethod::hash('abc'), $this->kit->clock->now())?->getId());
        self::assertNull($this->kit->links->consume(LinkMethod::hash('abc'), $this->kit->clock->now()), 'claimed once');
    }

    public function testAPasskeyRegisteredThenSigningInWithoutASecondFactor(): void
    {
        $user = $this->createUser();
        $this->kit->totpSecrets->enable($user, TotpMethod::secret());
        $authenticator = new SoftwareAuthenticator(AuthKit::ORIGIN);

        $enrolment = $this->kit->omniauth->enrol('passkey', $user);
        $options = $enrolment->begin(AuthKit::context());
        self::assertTrue($enrolment->confirm(AuthKit::context(), ['json' => json_encode($authenticator->create($options->payload)), 'label' => 'Software key'])->isDone());

        $this->entityManager()->clear();
        $row = $this->entityManager()->getConnection()->fetchAssociative('SELECT publicKeyCredentialId, userHandle, label, counter FROM userPasskey WHERE user_id = ?', [$user->getId()]);
        self::assertSame([base64_encode($authenticator->credentialId), (string) $user->getId(), 'Software key', $authenticator->counter], [$row['publicKeyCredentialId'], $row['userHandle'], $row['label'], (int) $row['counter']], 'the columns as the bundle writes them');
        self::assertCount(1, $this->kit->passkeys->of($this->kit->accounts->byHandle((string) $user->getId())));

        $request = $this->kit->omniauth->begin('passkey', AuthKit::context());
        $result = $this->kit->omniauth->verify('passkey', AuthKit::context(), ['json' => json_encode($authenticator->get($request->payload))]);
        self::assertTrue($result->isAuthenticated(), 'QA1: no second factor after a passkey, TOTP or not');
        self::assertSame($user->getId(), $result->account->getId());
        $this->entityManager()->clear();
        $passkey = $this->entityManager()->getRepository(Passkey::class)->findOneByCredential($authenticator->credentialId);
        self::assertSame($authenticator->counter, $passkey->counter, 'the counter the assertion gave');
        self::assertNotNull($passkey->getLastUsedAt());
    }

    public function testGoogleLinksByItsSubjectAndAsksTheSecondFactor(): void
    {
        $user = $this->createUser();
        $person = ['sub' => '1080'.$user->getId(), 'email' => $user->getEmail(), 'email_verified' => true, 'name' => 'Someone'];

        $result = $this->kit->google($person);
        self::assertTrue($result->isAuthenticated());
        $identity = $this->entityManager()->getRepository(Identity::class)->findOneBySubject('google', $person['sub']);
        self::assertSame([$user->getId(), $user->getEmail(), true], [$identity->getUser()->getId(), $identity->getEmail(), $identity->isEmailVerified()]);
        self::assertSame($this->kit->clock->now()->getTimestamp(), $identity->getLastUsedAt()?->getTimestamp());
        self::assertSame(['google'], $this->kit->identities->providers($user));
        self::assertNull($user->getIdentityProvider(), 'identityProvider keeps its meaning: where the account came from');

        // Later, another address at Google: the subject still finds the account; with a second factor, QA2.
        $this->kit->emailCodes->enable($user);
        self::assertSame(Status::SecondFactorRequired, $this->kit->google(['sub' => $person['sub'], 'email' => 'elsewhere@example.net', 'email_verified' => false])->status);

        // Unlinked from the settings: the subject finds nobody, and an account with a second factor is not linked by its address.
        $this->kit->identities->unlink($user, 'google');
        self::assertSame([], $this->kit->identities->providers($user));
        $refused = $this->kit->google($person);
        self::assertSame([Failure::NotEnrolled, 'link-from-settings'], [$refused->failure, $refused->reason]);
    }

    public function testAnAdministrationAccountIsNeverLinkedByItsAddress(): void
    {
        foreach (['ROLE_ADMIN', 'ROLE_SUPERADMIN'] as $role) {
            $admin = $this->createUser([$role]);
            $refused = $this->kit->google(['sub' => 'admin-'.$admin->getId(), 'email' => $admin->getEmail(), 'email_verified' => true]);

            self::assertSame([Failure::NotEnrolled, 'link-from-settings'], [$refused->failure, $refused->reason], $role);
            self::assertSame([], $this->kit->identities->providers($admin));
        }

        // A role a second factor is required of counts as one too.
        $kit = new AuthKit($this->entityManager(), $this->security(['ROLE_USER']));
        $member = $this->createUser();
        self::assertSame('link-from-settings', $kit->google(['sub' => 'm-'.$member->getId(), 'email' => $member->getEmail(), 'email_verified' => true])->reason);
    }

    public function testRefusedAccountsAndTheWelcomeBack(): void
    {
        $banned = $this->createUser();
        $banned->ban();
        $locked = $this->createUser();
        $locked->lock();
        $this->entityManager()->flush();

        self::assertSame([Failure::AccountRefused, UserAccounts::BANNED], [$this->password_($banned)->failure, $this->password_($banned)->reason]);
        self::assertSame(UserAccounts::LOCKED, $this->password_($locked)->reason);
        self::assertSame(Failure::BadCredentials, $this->kit->omniauth->verify('password', AuthKit::context(), ['identifier' => $banned->getEmail(), 'password' => 'wrong'])->failure, 'a wrong password says nothing of the account');

        $disabled = $this->createUser();
        $disabled->disable();
        $this->entityManager()->flush();
        $refused = $this->password_($disabled);
        self::assertSame(UserAccounts::DISABLED, $refused->reason);
        self::assertInstanceOf(AccountRefused::class, $this->kit->last(AccountRefused::class));
        $this->entityManager()->clear();
        $disabled = $this->entityManager()->find($disabled::class, $disabled->getId());
        self::assertNotNull($disabled->getValidToken(UserAccounts::WELCOME_BACK), 'WelcomeBack: the token of the e-mail');

        self::assertTrue($this->password_($disabled)->isAuthenticated(), 'its next sign-in within the hour enables it');
        self::assertFalse($this->entityManager()->find($disabled::class, $disabled->getId())->isDisabled());
    }

    public function testTheAdministratorWinsWhenASecondFactorIsTurnedOff(): void
    {
        $member = $this->createUser();
        $this->kit->emailCodes->enable($member);
        self::assertTrue($this->kit->omniauth->revoke('email-code', $member)->isDone(), 'free while nothing requires it');

        $kit = new AuthKit($this->entityManager(), $this->security(['ROLE_USER']));
        $kit->emailCodes->enable($member);
        $refused = $kit->omniauth->revoke('email-code', $member);
        self::assertSame([Failure::Refused, Policy::MANDATORY], [$refused->failure, $refused->reason]);
        self::assertTrue($member->isEmailAuthEnabled());

        // With an application beside it, the code by e-mail may go: a second factor remains.
        $kit->totpSecrets->enable($member, TotpMethod::secret());
        self::assertTrue($kit->omniauth->revoke('email-code', $member)->isDone());
        self::assertSame(Policy::MANDATORY, $kit->omniauth->revoke('totp', $member)->reason);
        self::assertTrue($kit->policy->needsEnrolment($this->createUser()), 'and an account of that role with none is sent to enrol');
    }
}
