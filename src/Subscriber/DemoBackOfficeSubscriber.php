<?php

namespace Base\Subscriber;

use Base\Demo\DemoAccountRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

/**
 * The back office in a demonstration (registered in the `demo` environment
 * only): open to nobody with a password everyone knows. Two tiers, a secret
 * each:
 *
 *  - the super-administrator, never a demonstration account, and never signed
 *    in with the password the fixtures gave it ("glitchr" / "glitchr"): its
 *    password is base.demo.superadmin_password (DEMO_SUPERADMIN_PASSWORD by
 *    default);
 *  - an account that opens the back office (base.demo.admin_roles - the
 *    shop's, the practice's, declared as a demonstration account or not):
 *    its password is base.demo.admin_password (DEMO_ADMIN_PASSWORD by
 *    default), the one given to whoever is shown the back office.
 *
 * What is typed is compared with the secret, the password stored in the
 * database is not looked at. While a tier's secret is not set, its accounts
 * cannot sign in at all, by any means.
 */
class DemoBackOfficeSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly DemoAccountRegistry $accounts,
        #[Autowire('%base.demo.superadmin_password%')] private readonly ?string $password = null,
        #[Autowire('%base.demo.admin_password%')] private readonly ?string $adminPassword = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // After the user is known (UserProviderListener, 1024) and checked
        // (UserCheckerListener, 256), before the stored password is (CheckCredentialsListener, 0).
        return [CheckPassportEvent::class => ['onCheckPassport', 128]];
    }

    public function onCheckPassport(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();
        if (!$passport->hasBadge(UserBadge::class)) {
            return;
        }

        try {
            $user = $passport->getUser();
        } catch (AuthenticationException) {
            return; // nobody of that name: the sign-in fails as usual
        }

        if ($this->accounts->isSuperAdmin($user)) {
            $secret = $this->password;
        } elseif ($this->accounts->isAdministrator($user)) {
            $secret = $this->adminPassword;
        } else {
            return;
        }

        if (null === $secret || '' === $secret) {
            throw new CustomUserMessageAuthenticationException('This account is not available in the demonstration.');
        }

        if (!$passport->hasBadge(PasswordCredentials::class)) {
            return; // a passkey or a "remember me" set up once signed in with the secret
        }

        /** @var PasswordCredentials $credentials */
        $credentials = $passport->getBadge(PasswordCredentials::class);
        if ($credentials->isResolved()) {
            return;
        }

        if (!hash_equals($secret, $credentials->getPassword())) {
            throw new BadCredentialsException('The presented password is invalid.');
        }

        $credentials->markResolved();
    }
}
