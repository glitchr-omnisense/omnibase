<?php

namespace Base\Auth\Account;

use Base\Entity\User;
use App\Entity\User\Notification;
use App\Entity\User\Token;
use Base\Notifier\NotifierInterface;
use Base\Repository\User\Tokens;
use Base\Repository\Users;
use Base\Service\SecurityPolicy;
use Omnibridge\Contract\Access;
use Omnibridge\Persistence\UnitOfWork;

/**
 * A forgotten password reset by a link, and a password the owner did not choose changed at the first sign-in,
 * written once for every host (plan "omnibase agnostic", step F5e, slice 5; formerly SecurityController::
 * ResetPasswordRequest(), ResetPasswordResponse() and ChangePassword()). The forms stay the host's; each
 * method is one of their moments. A demonstration account keeps its password, and says so.
 */
final class Passwords
{
    public const RESET_TOKEN = 'reset-password';
    /** The token user:create gives an account (Base\Console\Task\UserCreate::PASSWORD_CHANGE). */
    public const CHANGE_TOKEN = \Base\Console\Task\UserCreate::PASSWORD_CHANGE;
    public const LOGIN_ROUTE = 'security_login';

    public function __construct(
        private readonly Access $access,
        private readonly UnitOfWork $unitOfWork,
        private readonly Users $users,
        private readonly Tokens $tokens,
        private readonly SecurityPolicy $policy,
        private readonly ?NotifierInterface $notifier = null,
    ) {
    }

    /**
     * An address posted: a link sent by e-mail when it has an account and none is waiting, the one answer every
     * address gets (null: the page again); a demonstration account's, refused aloud.
     */
    public function resetRequested(?string $email): ?Outcome
    {
        $confirmation = new Notification('resetPassword.confirmation');
        $user = $this->users->oneByEmail($email);
        if (null !== $user && !$this->policy->canChangeCredentials($user)) {
            (new Notification('@notifications.demo.locked'))->send('warning');

            return Outcome::route(self::LOGIN_ROUTE);
        }

        if (null !== $user) {
            $user->removeExpiredTokens();
            if (!$user->getToken(self::RESET_TOKEN)) {
                $token = new Token(self::RESET_TOKEN, 3600);
                $token->setUser($user);
                // By e-mail alone: the page answers the same for every address.
                $this->notifier?->resetPasswordRequest($user, $token)->sendBy(['email+']);
            }
        }

        $this->unitOfWork->flush();
        $confirmation->send('success');

        return null;
    }

    /** The link's token when it is a reset link's, still valid, of an account that may change its password; else where to go. */
    public function resetToken(?string $value): Token|Outcome
    {
        $token = $this->tokens->oneByValue($value);
        if (null === $token || self::RESET_TOKEN !== $token->getName() || !$token->isValid()) {
            (new Notification('resetPassword.invalidToken'))->send('danger');

            return Outcome::index();
        }
        if (!$this->policy->canChangeCredentials($token->getUser())) {
            (new Notification('@notifications.demo.locked'))->send('warning');

            return Outcome::route(self::LOGIN_ROUTE);
        }

        return $token;
    }

    /** The new password chosen: the link used up, the account signed in (remembered), the index. */
    public function reset(Token $token, ?string $plainPassword): Outcome
    {
        $user = $token->getUser();
        $token->revoke();
        $user->setPlainPassword($plainPassword);
        $this->unitOfWork->flush();
        (new Notification('resetPassword.success'))->send('success');

        return Outcome::index()->signingIn($user, true);
    }

    /** The account's own token to change its password; else where to go (nobody signed in: the sign-in page). */
    public function changeToken(): Token|Outcome
    {
        $user = $this->access->getUser();
        if (!$user instanceof User) {
            return Outcome::route(self::LOGIN_ROUTE);
        }

        return $user->getValidToken(self::CHANGE_TOKEN) ?? Outcome::index();
    }

    /** The password chosen: the token used up, signed in again at once (a new password ends the session), the index. */
    public function change(Token $token, ?string $plainPassword): Outcome
    {
        $user = $token->getUser();
        $token->revoke();
        $user->setPlainPassword($plainPassword);
        $this->unitOfWork->flush();
        (new Notification('changePassword.success'))->send('success');

        return Outcome::index()->signingIn($user, true);
    }
}
