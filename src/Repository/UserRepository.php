<?php

namespace Base\Repository;

use App\Entity\User;
use Base\Contract\SecurityUserLoader;
use Base\Contract\SecurityUserProvider;
use Base\Database\Repository\ServiceEntityRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Base\Repository\Users;
use Base\Database\Repository\Finder\Group;

/**
 * @method User|null find($id)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * Security's user loader, user provider and password upgrader where
 * symfony/security-core is installed, through
 * Base\Contract\SecurityUserLoader and SecurityUserProvider (audit
 * "lesringostars face au master plan", R2): installed means declared, as for Base\Entity\User (C7b, C7c).
 * Elsewhere the repository loads without them, and its Security methods are
 * left uncalled.
 */
class UserRepository extends ServiceEntityRepository implements SecurityUserLoader, SecurityUserProvider, Users
{
    /** Users (step R15): the account of a username, through the finder's cache. */
    public function oneByUsername(string $username, bool $cached = true): ?\Base\Entity\User
    {
        return $cached ? $this->cacheOneByUsername($username) : $this->findOneByUsername($username);
    }

    public function oneByEmail(?string $email): ?\Base\Entity\User
    {
        return $this->findOneByEmail($email);
    }

    public function byIds(array $ids): array
    {
        return $this->cacheById($ids)->getResult();
    }

    public function activeWithin(int $seconds, ?int $exceptId = null): array
    {
        return (null !== $exceptId ? $this->findByIdNotEqualToAndActiveAtYoungerThan($exceptId, $seconds) : $this->findByActiveAtYoungerThan($seconds))->getResult();
    }

    public function oneById(int $id): ?\Base\Entity\User
    {
        return $this->cacheOneById($id);
    }

    public function byRoles(array|string $roles): array
    {
        return $this->cacheByRoles($roles)->getResult();
    }

    public function byUsernameContaining(string $term, int $limit = 50): array
    {
        if (!$this->shape()->hasField('username')) {
            return [];
        }

        // LOWER(username) LIKE %term%, the term's own % and _ taken literally.
        return $this->findByInsensitivePartialUsername('%' . addcslashes(mb_strtolower($term), '%_') . '%', [], ['username' => 'ASC'], $limit)->getResult();
    }

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

    public function refreshUser(UserInterface $user): UserInterface
    {
        $user = $this->cacheOneByEmail($user->getEmail());
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_debug_type($user)));
        }

        if ($user->isKicked()) {
            throw new UnsupportedUserException('You have been kicked..');
        }

        return $user;
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        // set the new hashed password on the User object
        $user->setPassword($newHashedPassword);

        $this->flush();
    }

    /**
     * OTHER admins (own ROLE_ADMIN/ROLE_SUPERADMIN column, not group-
     * inherited - a LIKE on the raw SET column can't see those without
     * joining group membership, and this is a "who else is around right
     * now" indicator, not a security check) active within the last
     * $onlineDelay seconds, most recently active first. $excludeId is
     * meant to be the current viewer - matches the one pre-existing
     * precedent for this kind of query (the older, currently-inert
     * AnalyticsSubscriber's findByIdNotEqualToAndActiveAtYoungerThan()).
     *
     * @return User[]
     */
    public function findActiveAdmins(int $onlineDelay, ?int $excludeId = null): array
    {
        $threshold = new \DateTime($onlineDelay . ' seconds ago');
        // activeAt > threshold AND (roles LIKE %ROLE_ADMIN% OR roles LIKE %ROLE_SUPERADMIN%) [AND id != excludeId]
        $criteria = [Group::by('PartialRolesOrPartialRoles', '%ROLE_ADMIN%', '%ROLE_SUPERADMIN%')];

        return (null === $excludeId
            ? $this->findByActiveAtGreaterThan($threshold, $criteria, ['activeAt' => 'DESC'])
            : $this->findByActiveAtGreaterThanAndIdNotEqualTo($threshold, $excludeId, $criteria, ['activeAt' => 'DESC'])
        )->getResult();
    }

    public function cacheByInsensitiveIdentifier($identifier, array $fields = []) { return $this->findByInsensitiveIdentifier($identifier, $fields, true); }
    public function findByInsensitiveIdentifier($identifier, array $fields = [], $cacheable = false)
    {
        if(empty($fields)) $fields[] = User::getUserIdentifierField();

        // LOWER(<field>) LIKE %identifier% on any of the fields: a finder of the language, one criterion per field.
        $identifier = "%".strtolower(preg_replace("/%$/", "", $identifier))."%";
        $finder = ($cacheable ? "cacheBy" : "findBy") . implode("Or", array_map(static fn (string $field) => "InsensitivePartial".ucfirst($field), $fields));

        return $this->{$finder}(...array_fill(0, \count($fields), $identifier));
    }
}
