<?php

namespace Base\Service;

use Base\Entity\User;
use Base\Entity\User\Group;
use Base\Repository\User\GroupRepository;
use Base\Repository\User\PermissionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The one service that turns a score into an actual capability.
 *
 * Everything up to here computes and grants nothing: Group::isOpenTo() says
 * whether somebody qualifies and Group::awardsFor() says what they would get,
 * both without touching them. Something has to close the gap, and in
 * fr.latoucheoriginale.www that something is a single service -
 * MarketplaceManager - which evaluates every candidate's scopes and rules,
 * then applies what its actions return. This is the same thing for people.
 *
 * The evaluation loop is deliberately the same shape as getPromotion()'s,
 * including its short-circuits, with one difference that matters: a promotion
 * stops at the first candidate that fits, because a product gets one
 * promotion. A person can qualify for several groups at once, so nothing
 * breaks out of the candidate loop here.
 *
 * Permission tags are resolved to rows here rather than in the adapter, which
 * is the whole reason GrantPermissionAdapter returns strings: an entity that
 * queries is an entity you cannot evaluate offline, so the lookup belongs in
 * the only layer that already has a repository.
 */
class GroupManager
{
    public function __construct(
        protected readonly GroupRepository $groupRepository,
        protected readonly PermissionRepository $permissionRepository,
        protected readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Every group that would admit this user as things stand.
     *
     * Includes ones they already belong to: this answers "do they qualify",
     * not "what is new", because a group they qualified for yesterday and no
     * longer do is exactly what a caller checking for revocation needs to see
     * missing from this list.
     *
     * @return Group[]
     */
    public function getEligibleGroups(User $user): array
    {
        $eligible = [];
        foreach ($this->groupRepository->findAll() as $group) {
            if ($group->isOpenTo($user)) {
                $eligible[] = $group;
            }
        }

        return $eligible;
    }

    /**
     * What those groups would award, merged across all of them.
     *
     * @return array{roles: string[], permissions: string[]}
     */
    public function getAwards(User $user): array
    {
        $roles = [];
        $permissions = [];

        foreach ($this->getEligibleGroups($user) as $group) {
            $awards = $group->awardsFor($user);
            $roles = array_merge($roles, $awards["roles"] ?? []);
            $permissions = array_merge($permissions, $awards["permissions"] ?? []);
        }

        return [
            "roles" => array_values(array_unique($roles)),
            "permissions" => array_values(array_unique($permissions)),
        ];
    }

    /**
     * Bring the user in line with what they have earned, and report what
     * actually changed.
     *
     * Additive by design. Nothing here removes a role, a permission or a
     * membership, because "no longer eligible" and "should be stripped" are
     * different decisions: a punitive score dip should not silently cost
     * somebody a rank they were given, and deciding otherwise is a policy
     * call for the caller rather than a side effect of a sync. getAwards()
     * above is what a caller compares against to make that call.
     *
     * Returns only the differences, so an unchanged user yields empty arrays
     * and a caller can flush, notify or log on that alone.
     *
     * @return array{groups: string[], roles: string[], permissions: string[]}
     */
    public function synchronize(User $user, bool $join = true): array
    {
        $joined = [];
        $granted = [];
        $permitted = [];

        foreach ($this->getEligibleGroups($user) as $group) {
            $awards = $group->awardsFor($user);

            if ($join && !$user->getGroups()->contains($group)) {
                $group->addMember($user);
                $joined[] = (string) $group->getName();
            }

            foreach ($awards["roles"] ?? [] as $role) {
                // Against the user's OWN roles: getRoles() folds in whatever
                // their groups grant, so comparing with that would decide a
                // role was already held the moment they joined the group that
                // implies it, and it would never be written down.
                if (!in_array($role, $user->getOwnRoles(), true)) {
                    // No addRole() on User - only the whole-array setter.
                    $user->setOwnRoles([...$user->getOwnRoles(), $role]);
                    $granted[] = $role;
                }
            }

            foreach ($awards["permissions"] ?? [] as $tag) {
                if ($permission = $this->permissionRepository->findOneByTag($tag)) {
                    if (!$user->getPermissions()->contains($permission)) {
                        $user->addPermission($permission);
                        $permitted[] = $tag;
                    }
                }
                // A tag with no row behind it is skipped rather than created:
                // permissions are an administrator's vocabulary, and a typo in
                // an action should not silently mint a new one.
            }
        }

        return [
            "groups" => $joined,
            "roles" => $granted,
            "permissions" => array_values(array_unique($permitted)),
        ];
    }
}
