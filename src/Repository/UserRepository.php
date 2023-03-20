<?php

namespace Base\Repository;

use Base\Entity\User;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;

use Base\Database\Repository\ServiceEntityRepository;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements UserLoaderInterface, UserProviderInterface, PasswordUpgraderInterface
{
    public function supportsClass(string $class): bool
    {
        return is_instanceof($class, User::class);
    }
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->findOneByEmail($identifier);
        if ($user === null) {
            throw new UserNotFoundException();
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): ?UserInterface
    {
        $user = $this->cacheOneByEmail($user->getEmail());
        return $user?->isKicked() ? null : $user;
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        // set the new hashed password on the User object
        $user->setPassword($newHashedPassword);

        // execute the queries on the database
        $this->getEntityManager()->flush();
    }
}
