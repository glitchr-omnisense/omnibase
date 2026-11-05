<?php

namespace Base\Auth\Policy;

use Base\Entity\User;

/**
 * The permission tags (plan "omnibase agnostic", step F5e, slice 1): custom fine-grained names, backed by
 * Base\Entity\User\Permission and granted on the user or through a group's membership ("ARTICLE.PUBLISH";
 * "ARTICLE.*" grants every "ARTICLE." one). Only that name space: never a ROLE_ or IS_ attribute (the role
 * hierarchy's) nor an EA_ one (omnibase/admin's), only upper-case names of letters, digits, _ . and :.
 */
class Permission
{
    public function supports(string $attribute): bool
    {
        return !str_starts_with($attribute, 'ROLE_')
            && !str_starts_with($attribute, 'IS_')
            && !str_starts_with($attribute, 'EA_')
            && 1 === preg_match('/^[A-Z][A-Z0-9_.:]*$/', $attribute);
    }

    public function decide(string $attribute, mixed $user): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        foreach ($this->tagsOf($user) as $tag) {
            if (0 === strcasecmp($tag, $attribute)) {
                return true;
            }
            // dotted namespacing: "ARTICLE.*" grants "ARTICLE.PUBLISH"
            if (str_ends_with($tag, '.*') && str_starts_with($attribute, substr($tag, 0, -1))) {
                return true;
            }
        }

        return false;
    }

    /** @return iterable<string> the user's own Permission tags, plus every group's */
    private function tagsOf(User $user): iterable
    {
        foreach ($user->getPermissions() as $permission) {
            yield $permission->getTag();
        }
        foreach ($user->getGroups() as $group) {
            foreach ($group->getPermissions() as $permission) {
                yield $permission->getTag();
            }
        }
    }
}
