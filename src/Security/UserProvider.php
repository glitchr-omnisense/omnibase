<?php

namespace Base\Security;

use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

use HWI\Bundle\OAuthBundle\Security\Core\User\OAuthAwareUserProviderInterface;
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

class UserProvider implements UserProviderInterface, PasswordUpgraderInterface, OAuthAwareUserProviderInterface
{
    /**
     * {@inheritdoc}
     */

    public function loadUserByIdentifier($response) : UserInterface
    {
        $data = $response->getData();

        $user = new User();
        $user->setId(0);
        $user->setRoles([UserRole::SOCIAL]);
        $user->setEmail($data["email"]);

        $user->verify($data["verified_email"]);

        $accessor = PropertyAccess::createPropertyAccessor();
        if ($accessor->isWritable($this, "username")) 
            $accessor->setValue($this, "username", "Google");

        $accessor = PropertyAccess::createPropertyAccessor();
        if ($accessor->isWritable($this, "username")) 
            $accessor->setValue($this, "username", $data["family_name"]);

        $accessor = PropertyAccess::createPropertyAccessor();
        if ($accessor->isWritable($this, "firstname")) 
            $accessor->setValue($this, "firstname", $data["given_name"]);

        return $user;
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByOAuthUserResponse(UserResponseInterface $response): UserInterface
    {
        return $this->loadUserByIdentifier($response);
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
    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof User)
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));

        return $user;
    }

    /**
     * Tells Symfony to use this provider for this User class.
     */
    public function supportsClass(string $class) : bool
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
}
