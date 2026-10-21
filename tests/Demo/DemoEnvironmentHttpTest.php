<?php

namespace Tests\Base\Demo;

use Base\Demo\DemoMode;
use Base\Exception\DemoRefusedException;
use Base\Service\SecurityPolicy;
use Tests\Base\Demo\Fixtures\TestDemoAccounts;

/**
 * The `demo` environment as a visitor meets it: the banner names the
 * demonstration account, the sign-in page lists the declared accounts, a
 * click signs in, the noindex header is there, the account's password and
 * second factor do not change, the staff's mandatory second factor is lifted
 * for it - and the back office is not public: its account and the
 * super-administrator are neither listed nor signed in without their secret.
 */
class DemoEnvironmentHttpTest extends DemoKernelTestCase
{
    private const ROOT = 'demo-root@example.org';
    private const ROOT_STORED_PASSWORD = 'the-password-the-fixtures-gave';
    private const BACK_OFFICE_SECRET = 'the-back-office-secret-example';

    protected function setUp(): void
    {
        $this->bootIn(DemoMode::ENVIRONMENT);
        $this->loadDemoAccounts();
        $this->createUser(self::ROOT, ['ROLE_SUPERADMIN'], self::ROOT_STORED_PASSWORD);
        $this->entityManager()->clear();
    }

    public function testTheSignInPageListsTheDeclaredAccountsAndNotTheBackOffice(): void
    {
        $response = $this->browse('/login');
        $html = (string) $response->getContent();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('data-demo-accounts', $html);
        $this->assertStringContainsString('Member of the public', $html);
        $this->assertStringContainsString('Books, pays, reads their own file.', $html);
        $this->assertStringContainsString('Professional', $html);
        $this->assertLessThan(strpos($html, 'Professional'), strpos($html, 'Member of the public'), 'in the declared order');
        $this->assertStringNotContainsString('Staff of the practice', $html, 'the back office is offered to nobody');
        $this->assertStringNotContainsString('demo-staff', $html);
        // In a narrow sign-in card the sentence goes under its button: the line folds, the sentence asks for 14rem.
        $this->assertMatchesRegularExpression('/\.base-demo-accounts form \{[^}]*flex-wrap: wrap/', $html);
        $this->assertMatchesRegularExpression('/\.base-demo-accounts form span \{[^}]*flex: 1 1 14rem/', $html);
        $this->assertStringNotContainsString('demo-root', $html, 'the super-administrator is nobody\'s demonstration account');
        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));

        $form = $this->demoForm($html, TestDemoAccounts::MEMBER);
        $this->assertStringEndsWith('/login/demo', $form['@action']);
        $this->assertNotEmpty($form['_token'], 'protected by a CSRF token');
    }

    public function testOneClickSignsIn(): void
    {
        $form = $this->demoForm((string) $this->browse('/login')->getContent(), TestDemoAccounts::MEMBER);

        $response = $this->browse($form['@action'], 'POST', ['_token' => $form['_token'], 'account' => $form['account']]);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringNotContainsString('/login', (string) $response->headers->get('Location'));

        $settings = $this->browse('/settings');
        $this->assertSame(200, $settings->getStatusCode());
        $this->assertSame(TestDemoAccounts::MEMBER, static::getContainer()->get('security.token_storage')->getToken()?->getUser()?->getEmail());
        $this->assertStringContainsString('data-demo-locked', (string) $settings->getContent(), 'its settings page says what does not change');
        $this->assertSame('noindex, nofollow', $settings->headers->get('X-Robots-Tag'));
    }

    public function testNoLinkSignsInAndNoRequestWithoutItsToken(): void
    {
        $form = $this->demoForm((string) $this->browse('/login')->getContent(), TestDemoAccounts::MEMBER);

        $this->assertContains($this->browse('/login/demo?account='.urlencode(TestDemoAccounts::MEMBER))->getStatusCode(), [404, 405], 'a GET signs nobody in: the route takes a POST only');

        $response = $this->browse('/login/demo', 'POST', ['_token' => 'forged', 'account' => TestDemoAccounts::MEMBER]);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringEndsWith('/login', (string) $response->headers->get('Location'));

        // The super-administrator, the back office's account and one nobody declared: not through here, token or not.
        foreach ([self::ROOT, TestDemoAccounts::STAFF, 'nobody@example.org'] as $identifier) {
            $response = $this->browse('/login/demo', 'POST', ['_token' => $form['_token'], 'account' => $identifier]);
            $this->assertStringEndsWith('/login', (string) $response->headers->get('Location'), $identifier);
        }

        $this->browse('/settings');
        $this->assertNull(static::getContainer()->get('security.token_storage')->getToken()?->getUser(), 'nobody is signed in');
    }

    public function testADemonstrationAccountKeepsItsPasswordItsAddressAndItsSecondFactor(): void
    {
        $form = $this->demoForm((string) $this->browse('/login')->getContent(), TestDemoAccounts::MEMBER);
        $this->browse($form['@action'], 'POST', ['_token' => $form['_token'], 'account' => $form['account']]);

        $em = $this->entityManager();
        $em->clear();
        $before = $em->getRepository('App\\Entity\\User')->findOneBy(['email' => TestDemoAccounts::MEMBER]);
        $hash = $before->getPassword();
        $id = $before->getId();

        // The pages of omnibase refuse: the second factor by e-mail, closing the account.
        $settings = (string) $this->browse('/settings')->getContent();
        $this->assertStringNotContainsString('/settings/2fa/email', $settings, 'its settings page offers no switch');
        $this->assertStringNotContainsString('href="/settings/2fa"', $settings);
        $response = $this->browse('/settings/2fa/email', 'POST');
        $this->assertSame(302, $response->getStatusCode(), 'refused, and sent back to the settings');
        $this->assertStringEndsWith('/settings', (string) $response->headers->get('Location'));
        $this->browse('/account-goodbye');

        // Any other form that would write them - the application's own "my account" page: put back before the row is written.
        $warnings = $this->duringARequest('/mon-compte', function () use ($em, $id): void {
            $em->clear();
            $user = $em->find('App\\Entity\\User', $id);
            $user->setPlainPassword('A-new-password-nobody-else-knows-1!');
            $user->setEmail('mine-now@example.org');
            $user->setTotpSecret('JBSWY3DPEHPK3PXP'); // RFC 4648's example, no account's secret
            $user->setEmailAuthEnabled(true);
            $em->flush();
        });
        $this->assertCount(1, $warnings, 'said once, in a flash message');

        $em->clear();
        $after = $em->find('App\\Entity\\User', $id);
        $this->assertSame($hash, $after->getPassword(), 'the password is the one everybody signs in with');
        $this->assertSame(TestDemoAccounts::MEMBER, $after->getEmail());
        $this->assertFalse($after->isTotpAuthenticationEnabled());
        $this->assertFalse($after->isEmailAuthEnabled());
        $this->assertSame($before->isEnabled(), $after->isEnabled(), 'not closed');

        // The same password typed again is no change, and no warning.
        $this->assertSame([], $this->duringARequest('/mon-compte', function () use ($em, $after): void {
            $after->setPlainPassword(TestDemoAccounts::MEMBER);
            $em->flush();
        }));

        // And it is not deleted.
        try {
            $this->duringARequest('/admin/users/delete', function () use ($em, $after): void {
                $em->remove($after);
                $em->flush();
            });
            $this->fail('a demonstration account was deleted');
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException) {
            $em->clear();
        }
        $this->assertNotNull($em->find('App\\Entity\\User', $id));
    }

    public function testTheStaffsMandatorySecondFactorIsLiftedForItAndForItAlone(): void
    {
        self::ensureKernelShutdown();
        $_SERVER['DEMO_ADMIN_PASSWORD'] = $_ENV['DEMO_ADMIN_PASSWORD'] = self::BACK_OFFICE_SECRET;
        $this->bootIn(DemoMode::ENVIRONMENT);

        $policy = static::getContainer()->get(SecurityPolicy::class);
        $this->assertSame(['ROLE_ADMIN'], $policy->getRequiredRoles());

        // An administrator of the site's own is sent to enrol...
        $colleague = $this->createUser('demo-colleague@example.org', ['ROLE_ADMIN'], 'colleague-password-example');
        $this->assertTrue($policy->needsEnrolment($colleague));

        // ...the demonstration's member of staff, signed in with the back office's secret, walks in.
        $this->signIn(TestDemoAccounts::STAFF, self::BACK_OFFICE_SECRET);

        $response = $this->browse('/profile');
        $this->assertStringNotContainsString('/settings/security-required', (string) $response->headers->get('Location'));
        $staff = static::getContainer()->get('security.token_storage')->getToken()?->getUser();
        $this->assertSame(TestDemoAccounts::STAFF, $staff?->getEmail());
        $this->assertContains('ROLE_ADMIN', $staff->getRoles());
        $this->assertFalse($policy->needsEnrolment($staff));
    }

    public function testTheSuperAdministratorSignsInWithTheSecretOrNotAtAll(): void
    {
        // No secret: the account is unusable, with the password the fixtures gave it too.
        $response = $this->signIn(self::ROOT, self::ROOT_STORED_PASSWORD);
        $this->assertStringEndsWith('/login', (string) $response->headers->get('Location'));
        $this->browse('/settings');
        $this->assertNull(static::getContainer()->get('security.token_storage')->getToken()?->getUser());

        // The secret set: it is the password; the stored one opens nothing.
        self::ensureKernelShutdown();
        $_SERVER['DEMO_SUPERADMIN_PASSWORD'] = $_ENV['DEMO_SUPERADMIN_PASSWORD'] = 'not-a-real-secret-example';
        $this->bootIn(DemoMode::ENVIRONMENT);

        $response = $this->signIn(self::ROOT, self::ROOT_STORED_PASSWORD);
        $this->assertStringEndsWith('/login', (string) $response->headers->get('Location'));

        $response = $this->signIn(self::ROOT, 'not-a-real-secret-example');
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringNotContainsString('/login', (string) $response->headers->get('Location'));
        $this->browse('/settings');
        $this->assertSame(self::ROOT, static::getContainer()->get('security.token_storage')->getToken()?->getUser()?->getEmail());
    }

    public function testTheBackOfficesAccountSignsInWithItsSecretOrNotAtAll(): void
    {
        // No secret: the password everyone could guess - its identifier - opens nothing.
        $response = $this->signIn(TestDemoAccounts::STAFF, TestDemoAccounts::STAFF);
        $this->assertStringEndsWith('/login', (string) $response->headers->get('Location'));
        $this->browse('/settings');
        $this->assertNull(static::getContainer()->get('security.token_storage')->getToken()?->getUser());

        // The secret set: it is the password...
        self::ensureKernelShutdown();
        $_SERVER['DEMO_ADMIN_PASSWORD'] = $_ENV['DEMO_ADMIN_PASSWORD'] = self::BACK_OFFICE_SECRET;
        $this->bootIn(DemoMode::ENVIRONMENT);

        $response = $this->signIn(TestDemoAccounts::STAFF, self::BACK_OFFICE_SECRET);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringNotContainsString('/login', (string) $response->headers->get('Location'));
        $this->browse('/settings');
        $this->assertSame(TestDemoAccounts::STAFF, static::getContainer()->get('security.token_storage')->getToken()?->getUser()?->getEmail());

        // ...the stored one still opens nothing, and the super-administrator's secret stays its own.
        self::ensureKernelShutdown();
        $this->bootIn(DemoMode::ENVIRONMENT);
        foreach ([[TestDemoAccounts::STAFF, TestDemoAccounts::STAFF], [self::ROOT, self::BACK_OFFICE_SECRET]] as [$identifier, $password]) {
            $response = $this->signIn($identifier, $password);
            $this->assertStringEndsWith('/login', (string) $response->headers->get('Location'), $identifier);
        }
        $this->browse('/settings');
        $this->assertNull(static::getContainer()->get('security.token_storage')->getToken()?->getUser());
    }

    public function testTheBackOfficeOpensToItsOwnAccountAlone(): void
    {
        // Signed in at a click: the back office answers 403, page and sub-page.
        $form = $this->demoForm((string) $this->browse('/login')->getContent(), TestDemoAccounts::MEMBER);
        $this->browse($form['@action'], 'POST', ['_token' => $form['_token'], 'account' => $form['account']]);
        $this->browse('/settings');
        $this->assertSame(TestDemoAccounts::MEMBER, static::getContainer()->get('security.token_storage')->getToken()?->getUser()?->getEmail());
        foreach (['/admin', '/admin/users'] as $path) {
            $this->assertSame(403, $this->browse($path)->getStatusCode(), $path);
        }
        $this->assertSame(200, $this->browse('/settings')->getStatusCode(), 'the rest of the site is open');

        // Its own account, signed in with the secret: not refused here.
        self::ensureKernelShutdown();
        $_SERVER['DEMO_ADMIN_PASSWORD'] = $_ENV['DEMO_ADMIN_PASSWORD'] = self::BACK_OFFICE_SECRET;
        $this->bootIn(DemoMode::ENVIRONMENT);
        $this->signIn(TestDemoAccounts::STAFF, self::BACK_OFFICE_SECRET);
        $this->assertNotSame(403, $this->browse('/admin')->getStatusCode());
    }

    public function testTheBannerIsPrintedAndNamesTheDemonstrationAccountToAVisitor(): void
    {
        $html = static::getContainer()->get('twig')->render('@Base/demo/_banner.html.twig');

        $this->assertStringContainsString('data-demo-banner', $html);
        $this->assertMatchesRegularExpression('/Démonstration|Demonstration/', $html);
        $this->assertMatchesRegularExpression('/remises à zéro chaque nuit|reset every night/', $html);

        // The first account offered, with what it signs in with, and the way to the sign-in page - where the other role is.
        $this->assertStringContainsString('data-demo-notice="'.TestDemoAccounts::MEMBER.'"', $html);
        $this->assertStringContainsString('Member of the public', $html);
        $this->assertMatchesRegularExpression('/(identifiant|identifier) '.preg_quote(TestDemoAccounts::MEMBER, '/').', (mot de passe|password) '.preg_quote(TestDemoAccounts::MEMBER, '/').'/', $html);
        $this->assertMatchesRegularExpression('#<a href="[^"]*/login">(Se connecter ou choisir un autre rôle|Sign in or pick another role)</a>#', $html);
        $this->assertStringNotContainsString('demo-staff', $html, 'never the back office\'s');
        $this->assertStringNotContainsString('<form', $html, 'no form: no token, no session on a public page');

        // On the sign-in page itself the accounts are right under the form: no link back to it.
        $signIn = (string) $this->browse('/login')->getContent();
        $this->assertStringNotContainsString('pick another role</a>', $signIn);
        $this->assertStringNotContainsString('choisir un autre rôle</a>', $signIn);

        // Signed in, nobody is told how to.
        $form = $this->demoForm($signIn, TestDemoAccounts::MEMBER);
        $this->browse($form['@action'], 'POST', ['_token' => $form['_token'], 'account' => $form['account']]);
        $this->browse('/settings');
        $this->assertNotNull(static::getContainer()->get('security.token_storage')->getToken()?->getUser());
        $html = static::getContainer()->get('twig')->render('@Base/demo/_banner.html.twig');
        $this->assertStringContainsString('data-demo-banner', $html);
        $this->assertStringNotContainsString('data-demo-notice', $html);
    }

    public function testItDoesNotStartOnTheProductionDatabase(): void
    {
        $connection = static::getContainer()->get('doctrine')->getConnection();
        $params = $connection->getParams();
        $own = isset($params['path']) ? 'sqlite:///'.ltrim($params['path'], '/') : sprintf('mysql://%s:%s/%s', $params['host'] ?? 'localhost', $params['port'] ?? 3306, $params['dbname'] ?? '');

        self::ensureKernelShutdown();
        $_SERVER['DEMO_TEST_PRODUCTION_DATABASE'] = $_ENV['DEMO_TEST_PRODUCTION_DATABASE'] = $own;

        try {
            self::bootKernel(['environment' => DemoMode::ENVIRONMENT, 'debug' => false]);
            $this->fail('the demonstration started on the database named as production\'s');
        } catch (DemoRefusedException $e) {
            $this->assertStringContainsString('does not start on the production database', $e->getMessage());
        } finally {
            unset($_SERVER['DEMO_TEST_PRODUCTION_DATABASE'], $_ENV['DEMO_TEST_PRODUCTION_DATABASE']);
            // The refused kernel never booted, and would refuse again (its container read the variable): dropped, not shut down.
            static::$kernel = null;
            static::$booted = false;
            // Booted again as it was, for tearDown() to remove what setUp() created.
            $this->bootIn(DemoMode::ENVIRONMENT);
        }
    }
}
