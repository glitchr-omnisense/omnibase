<?php

namespace Base\Auth\Account;

use Base\Entity\User;
use App\Entity\User\Notification;
use App\Entity\User\Token;
use Base\Notifier\NotifierInterface;
use Base\Repository\User\Tokens;
use Base\Repository\Users;
use Base\Service\ParameterBagInterface;
use Omnibridge\Contract\Access;
use Omnibridge\Persistence\UnitOfWork;

/**
 * The sign-in page's rules and the sign-in link's, written once for every host (plan "omnibase agnostic", step
 * F5e, slice 5; formerly SecurityController::Login() and LoginTokenRequest()). The forms, the captcha and
 * omniauth's link (where omniauth signs in, it issues the links: the host asks it) stay the host's.
 */
final class SignIn
{
    /** The page an account allowed in during a maintenance signs in on (RescueFormAuthenticator::LOGIN_ROUTE). */
    public const RESCUE_ROUTE = 'security_rescue';
    public const LOGIN_ROUTE = 'security_login';
    /** The sign-in link's token: its name, how long it lives, how long before another is sent. */
    public const LINK_TOKEN = 'login-token';
    public const LINK_LIFETIME = 30 * 24 * 3600;
    public const LINK_THROTTLE = 3600;

    public function __construct(
        private readonly Access $access,
        private readonly ParameterBagInterface $parameters,
        private readonly UnitOfWork $unitOfWork,
        private readonly Users $users,
        private readonly Tokens $tokens,
        private readonly ?NotifierInterface $notifier = null,
    ) {
    }

    /**
     * Before the sign-in page: an account allowed in during a maintenance goes to the rescue page; an account
     * signed in fully goes where it was going; one remembered only is told to sign in again (login.partial).
     */
    public function page(): ?Outcome
    {
        if ($this->access->isGranted('EXCEPTION_ACCESS')) {
            return Outcome::route(self::RESCUE_ROUTE);
        }

        $user = $this->access->getUser();
        if (null === $user) {
            return null;
        }
        if ($user instanceof User) {
            $user->removeExpiredTokens();
        }
        if ($this->access->isGranted('IS_AUTHENTICATED_FULLY')) {
            return Outcome::back();
        }

        (new Notification('login.partial'))->send('info');

        return null;
    }

    /** Whether the site signs in by a link sent by e-mail (base.user.login_with_token). */
    public function linksEnabled(): bool
    {
        return (bool) $this->parameters->get('base.user.login_with_token');
    }

    /**
     * A sign-in link followed: off, the sign-in page; a valid token, its account signed in, remembered, then
     * where it was going; null for the page asking an address (no token, or one omnibase did not issue).
     */
    public function link(?string $token, bool $byOmniauth = false): ?Outcome
    {
        if (!$this->linksEnabled()) {
            return Outcome::route(self::LOGIN_ROUTE);
        }

        $loginToken = $byOmniauth ? null : $this->tokens->oneSigningInByNameAndValue(self::LINK_TOKEN, $token);
        if (null !== $loginToken && $loginToken->isValid() && null !== $loginToken->getUser()) {
            return Outcome::back()->signingIn($loginToken->getUser(), true);
        }

        return null;
    }

    /**
     * An address posted for a link (where omnibase issues them): a link sent to it by e-mail when it has an
     * account and none is waiting; then the one answer every address gets.
     */
    public function linkRequested(?string $email, bool $byOmniauth = false): Outcome
    {
        if (!$byOmniauth && null !== ($user = $this->users->oneByEmail($email))) {
            $user->removeExpiredTokens();
            if (!$user->getToken(self::LINK_TOKEN)) {
                $loginToken = new Token(self::LINK_TOKEN, self::LINK_LIFETIME, self::LINK_THROTTLE);
                $loginToken->markAsLogginable();
                $loginToken->setUser($user);
                $this->unitOfWork->flush();

                // By e-mail alone: the page says the same for an address that has no account.
                $this->notifier?->loginToken($user, $loginToken)->sendBy(['email+']);
            }
        }

        (new Notification('loginToken.confirmation'))->send('success');

        return Outcome::back();
    }
}
