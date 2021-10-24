<?php

namespace Base\Repository;

use Base\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;

use Base\Database\Repository\ServiceEntityRepository;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements UserLoaderInterface, PasswordUpgraderInterface
{
    // TODO: Remove the two next methods in S6.0
    public function loadUserByUsername(string $email) { return $this->loadUserByIdentifier($email); }
    // TODO-END

    public function findByRoles(string $role)
    {
        $qb = $this->_em->createQueryBuilder();

        $qb->select('u')
        ->from('Base\Entity\User', 'u')
        ->where('u.roles LIKE :roles')
        ->setParameter('roles', '%"' . $role . '"%');

        return $qb->getQuery()->getResult();
    }

    public function loadUserByIdentifier($email) {

        return $this->getEntityManager()->createQuery(
            'SELECT u
                FROM App\Entity\User u
                WHERE u.email = :email'
        )
            ->setParameter('email', $email)
            ->getOneOrNullResult();
    }

    public function upgradePassword(UserInterface $user, string $newHashedPassword): void
    {
        // set the new hashed password on the User object
        $user->setPassword($newHashedPassword);

        // execute the queries on the database
        $this->getEntityManager()->flush();
    }
}
