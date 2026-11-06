<?php

namespace Base\Auth\Account;

use Base\Entity\User;
use App\Entity\User\Notification;
use Base\Repository\User\Tokens;
use Base\Service\SecurityPolicy;
use Omnibridge\Contract\Access;
use Omnibridge\Persistence\UnitOfWork;

/**
 * An account closed by its owner, and opened again by the link it is sent, written once for every host (plan
 * "omnibase agnostic", step F5e, slice 5; formerly SecurityController::DisableAccountRequest() and
 * EnableAccountRequest()). A demonstration account is everyone's: it does not close itself.
 */
final class Closing
{
    public const TOKEN = 'welcome-back';

    public function __construct(
        private readonly Access $access,
        private readonly UnitOfWork $unitOfWork,
        private readonly Tokens $tokens,
        private readonly SecurityPolicy $policy,
    ) {
    }

    public function close(): Outcome
    {
        $user = $this->access->getUser();
        if (!$this->policy->canChangeCredentials($user)) {
            (new Notification('@notifications.demo.locked'))->send('warning');
        } elseif (!$user instanceof User) {
            return Outcome::denied();
        } elseif ($user->isDisabled()) {
            (new Notification('accountGoodbye.already'))->send('warning');
        } else {
            $user->disable();
            $user->logout();
            $this->unitOfWork->flush();
        }

        return Outcome::index();
    }

    /** The link "welcome back": a closed account with its valid token opened and signed in; otherwise said why. */
    public function welcomeBack(?string $value): Outcome
    {
        $token = $this->tokens->oneByValueAndName($value, self::TOKEN);
        $user = null !== $token ? $token->getUser() : $this->access->getUser();

        if ($user instanceof User && !$user->isDisabled()) {
            $token?->revoke();
            (new Notification('accountWelcomeBack.already'))->send('warning');

            return Outcome::index();
        }
        if ($user instanceof User && $user->getValidToken(self::TOKEN)) {
            $user->enable();
            $this->unitOfWork->flush();

            return Outcome::signedIn($user);
        }

        $token?->revoke();
        (new Notification('accountWelcomeBack.invalidToken'))->send('danger');
        $this->unitOfWork->flush();

        return Outcome::index();
    }
}
