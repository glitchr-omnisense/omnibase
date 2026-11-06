<?php

namespace Base\Auth\Account;

use Base\Entity\User;
use App\Entity\User\Notification;
use App\Entity\User\Token;
use Base\Notifier\NotifierInterface;
use Base\Repository\User\Tokens;
use Omnibridge\Contract\Access;
use Omnibridge\Persistence\UnitOfWork;

/**
 * An account's e-mail address verified, then its approval asked of the administrators, written once for every
 * host (plan "omnibase agnostic", step F5e, slice 5; formerly SecurityController::VerifyEmailRequest(),
 * VerifyEmailResponse() and AdminApprovalRequest()). The link's account is signed in by the host between
 * tokenOwner() and confirm(). Each step ends on the account's profile.
 */
final class Verification
{
    public const TOKEN = 'verify-email';
    public const APPROVAL_TOKEN = 'admin-approval';
    public const PROFILE_ROUTE = 'user_profile';
    public const REQUEST_ROUTE = 'security_verifyEmail';

    public function __construct(
        private readonly Access $access,
        private readonly UnitOfWork $unitOfWork,
        private readonly Tokens $tokens,
        private readonly ?NotifierInterface $notifier = null,
    ) {
    }

    /** A verification link asked: already verified, said so; one sent less than an hour ago, said when; else one sent. */
    public function request(): Outcome
    {
        $user = $this->access->getUser();
        if (!$user instanceof User) {
            return Outcome::denied();
        }

        if ($user->isVerified()) {
            (new Notification('verifyEmail.already'))->send('info');
        } elseif (($token = $user->getToken(self::TOKEN)) && $token->hasVeto()) {
            (new Notification('verifyEmail.resend', [$token->getThrottleTimeStr()]))->send('danger');
        } else {
            $token = new Token(self::TOKEN, 24 * 3600, 3600);
            $token->setUser($user);
            $this->notifier?->sendVerificationEmail($user, $token)->send('success');
        }

        $this->unitOfWork->flush();

        return Outcome::route(self::PROFILE_ROUTE);
    }

    /** The link's token, the host signing its account in before confirm(). */
    public function tokenOwner(?string $value): ?Token
    {
        return $this->tokens->oneByValueAndName($value, self::TOKEN);
    }

    /**
     * The link followed: its account (signed in now) verified when the token is its valid one, then its approval
     * asked where an administrator has to approve it; nobody signed in, back to asking a link.
     */
    public function confirm(?Token $token): Outcome
    {
        $user = $this->access->getUser();
        if (!$user instanceof User) {
            (new Notification('verifyEmail.invalidToken'))->send('danger');

            return Outcome::route(self::REQUEST_ROUTE);
        }

        $user->removeExpiredTokens();
        if ($user->isVerified()) {
            self::tell('verifyEmail.already', $user, 'info');
        } elseif (null === $token || null === ($valid = $user->getValidToken(self::TOKEN)) || $valid->get() != $token->get()) {
            self::tell('verifyEmail.invalidToken', $user, 'danger');
        } else {
            $user->verify();
            $valid->revoke();
            self::tell('verifyEmail.success', $user, 'success');

            if (!$user->isApproved()) {
                $this->approval();
            }
        }

        $this->unitOfWork->flush();

        return Outcome::route(self::PROFILE_ROUTE);
    }

    /** The administrators asked to approve the account: once verified, and once. */
    public function approval(): Outcome
    {
        $user = $this->access->getUser();
        if (!$user instanceof User) {
            return Outcome::denied();
        }

        $user->removeExpiredTokens();
        if (!$user->isVerified()) {
            (new Notification('adminApproval.verifyFirst'))->send('warning');
        } elseif (!$user->isApproved()) {
            if ($user->getValidToken(self::APPROVAL_TOKEN)) {
                (new Notification('adminApproval.alreadySent'))->send('warning');
            } else {
                (new Token(self::APPROVAL_TOKEN))->setUser($user);
                $this->notifier?->sendUserApprovalRequest($user)->send('success');
            }
        }

        $this->unitOfWork->flush();

        return Outcome::route(self::PROFILE_ROUTE);
    }

    private static function tell(string $message, User $user, string $importance): void
    {
        $notification = new Notification($message);
        $notification->setUser($user);
        $notification->send($importance);
    }
}
