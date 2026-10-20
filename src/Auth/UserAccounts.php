<?php

namespace Base\Auth;

use App\Entity\User as AppUser;
use Base\Entity\User;
use Base\Security\LoginRestrictionInterface;
use Omniauth\Contract\Accounts;
use Omniauth\Exception\AccountRefused;

/**
 * omniauth's accounts: omnibase's users (the `user` table, and the
 * application's own beside it - lesringostars' userProfile, joined).
 *
 * - byIdentifier(): the address, as Base\Repository\UserRepository's
 *   loadUserByIdentifier() finds it, then the application's identifier field
 *   when it is another (User::$userIdentifier: lesringostars' username), as
 *   its own repository does (findOneByEmailOrUsername()).
 * - the handle: the id, as text - the passkeys' userHandle since AU1.
 * - check(): Base\Security\UserChecker's rule - an account class that
 *   implements LoginRestrictionInterface, a banned, a locked, a disabled
 *   account may not sign in; the reason is the key of its message,
 *   "@notifications.login.<reason>". A disabled account with a valid
 *   "welcome-back" token is enabled again (the e-mail that token came with
 *   is Listener\WelcomeBack's, on omniauth's AccountRefused event).
 */
class UserAccounts extends UserStore implements Accounts
{
    public const RESTRICTED = 'restricted';
    public const BANNED = 'banned';
    public const LOCKED = 'locked';
    public const DISABLED = 'disabled';

    /** The token that lets a disabled account back in (UserChecker's). */
    public const WELCOME_BACK = 'welcome-back';

    public function byIdentifier(string $identifier): ?object
    {
        $identifier = trim($identifier);
        if ('' === $identifier) {
            return null;
        }
        $repository = $this->entityManager->getRepository(AppUser::class);
        $user = $repository->findOneBy(['email' => mb_strtolower($identifier)]);
        $field = AppUser::getUserIdentifierField();
        if (null === $user && 'email' !== $field && $this->entityManager->getClassMetadata(AppUser::class)->hasField($field)) {
            $user = $repository->findOneBy([$field => $identifier]);
        }

        return $user instanceof User ? $user : null;
    }

    public function byHandle(string $handle): ?object
    {
        if (!ctype_digit($handle)) {
            return null;
        }
        $user = $this->entityManager->find(AppUser::class, (int) $handle);

        return $user instanceof User ? $user : null;
    }

    public function handle(object $account): string
    {
        $id = $this->user($account)->getId();

        return null !== $id ? (string) $id : throw new \LogicException('An account signs in once it is saved: it has no id yet.');
    }

    public function identifier(object $account): string
    {
        return $this->user($account)->getUserIdentifier();
    }

    public function displayName(object $account): string
    {
        $user = $this->user($account);
        $name = method_exists($user, 'getFullname') ? $user->getFullname() : null;

        return \is_string($name) && '' !== trim($name) ? $name : (string) $user;
    }

    public function email(object $account): ?string
    {
        return $this->user($account)->getEmail();
    }

    public function check(object $account): void
    {
        $user = $this->user($account);
        if ($user instanceof LoginRestrictionInterface) {
            throw new AccountRefused(self::RESTRICTED);
        }
        if ($user->isBanned()) {
            throw new AccountRefused(self::BANNED);
        }
        if ($user->isLocked()) {
            throw new AccountRefused(self::LOCKED);
        }
        if ($user->isDisabled()) {
            if (null !== $user->getValidToken(self::WELCOME_BACK)) {
                $user->enable();
                $this->save();

                return;
            }
            throw new AccountRefused(self::DISABLED);
        }
    }
}
