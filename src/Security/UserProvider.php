<?php

namespace Base\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

use HWI\Bundle\OAuthBundle\Security\Core\User\OAuthAwareUserProviderInterface;
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use App\Repository\UserRepository;

class UserProvider implements UserProviderInterface, PasswordUpgraderInterface, OAuthAwareUserProviderInterface
{
    /**
     * @var UserTracker
     */
    protected UserTracker $userTracker;

    /**
     * @var UserRepository
     */
    protected UserRepository $userRepository;

    public function __construct(UserTracker $userTracker, UserRepository $userRepository)
    {
        $this->userTracker = $userTracker;
        $this->userRepository = $userRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByIdentifier($identifier): UserInterface
    {
        throw new \Exception('TODO: fill in loadUserByIdentifier() inside '.__FILE__);
    }

    /**
     * Refreshes the user after being reloaded from the session.
     *
     * When a user is logged in, at the beginning of each request, the
     * User object is loaded from the session and then this method is
     * called. Your job is to make sure the user's data is still fresh by,
     * for example, re-querying for fresh User data.
     *
     * If your firewall is "stateless: true" (for a pure API), this
     * method is not called.
     *
     * @return UserInterface
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        $this->userTracker->updateConnection($user);

        return $user;
    }

    /**
     * Tells Symfony to use this provider for this User class.
     */
    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }

    /**
     * Upgrades the encoded password of a user, typically for using a better hash algorithm.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        // set the new hashed password on the User object
        $user->setPassword($newHashedPassword);
    }


    /**
     * {@inheritdoc}
     */
    public function loadUserByOAuthUserResponse(UserResponseInterface $response): UserInterface
    {
        $identityProvider = $response->getResourceOwner()->getName();
        $email = $response->getEmail();

        if ($email && ($user = $this->userRepository->findOneByEmail($email))) {

            // Link this IdP to an existing local (or previously-linked) account.
            if ($user->getIdentityProvider() === null) {
                $user->setIdentityProvider($identityProvider);
            }

            return $user;
        }

        // No local account yet: return a transient, unpersisted stub. The oauth firewall's
        // default_target_path (/register) lets the user complete registration (pick a username)
        // before anything is written to the database - isPersistent() (id === 0 here) reflects that.
        $user = new User();
        $user->setId(0);
        $user->setIdentityProvider($identityProvider);
        $user->setEmail($email);
        $user->setFirstname($response->getFirstName());
        $user->setLastname($response->getLastName());
        $user->verify(true); // Google already verified ownership of this email address

        return $user;
    }
}
