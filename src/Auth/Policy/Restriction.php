<?php

namespace Base\Auth\Policy;

/**
 * What a request meets while the site's access is restricted (plan "omnibase agnostic", step F5e, slice 1):
 * the decision omnibridge/symfony's SecuritySubscriber::onAccessRequest() took, for every host.
 *
 * The access is restricted when ADMIN_ACCESS, USER_ACCESS or ANONYMOUS_ACCESS is refused (Access). Then:
 * a user granted one of them for themselves has a special grant (the profiler stays on for them); the
 * sign-in's pages stay open; the back office and the profiler answer "not found" to whoever is refused
 * BACKEND; an exception of the restriction opens the page (its special grantee told so); a page that is
 * not one the denied are sent to sends them to the first of those (else the sign-in), lets a special
 * grantee in (a super administrator warned outside the back office); on one of those pages a special
 * grantee is told that the others are sent there.
 */
final class Restriction
{
    /** @param \Closure(string, mixed=): bool $granted the host's decision on an attribute, for the current account or the subject given */
    public function __construct(private readonly \Closure $granted)
    {
    }

    public function decide(mixed $user, Page $page): RestrictionDecision
    {
        $granted = $this->granted;
        $adminAccess = $granted(Access::ADMIN_ACCESS);
        $userAccess = $granted(Access::USER_ACCESS);
        $anonymousAccess = $granted(Access::ANONYMOUS_ACCESS);
        if ($adminAccess && $userAccess && $anonymousAccess) {
            return RestrictionDecision::open();
        }

        $type = !$adminAccess ? 'admin_restriction' : (!$userAccess ? 'user_restriction' : 'public_restriction');

        $specialGrant = $granted(Access::ANONYMOUS_ACCESS, $user);
        if ($user && !$specialGrant) {
            $specialGrant = $granted(Access::USER_ACCESS, $user);
        }
        if ($user && !$specialGrant) {
            $specialGrant = $granted(Access::ADMIN_ACCESS, $user);
        }
        $profiler = (bool) $specialGrant;

        if ($page->isSecurity()) {
            return RestrictionDecision::open($profiler);
        }
        if ($page->isProfiler() && !$granted(Admin::BACKEND)) {
            return RestrictionDecision::notFound($profiler);
        }
        if ($page->isAdmin() && !$granted(Admin::BACKEND)) {
            return RestrictionDecision::notFound($profiler);
        }
        if (!$page->isProfiler() && !$page->isAdmin() && $granted(Access::EXCEPTION_ACCESS)) {
            return RestrictionDecision::open($profiler, $specialGrant ? ['access_restricted.'.$type.'.exception', 'info'] : null);
        }

        $redirects = $page->redirectsOnDeny();
        if (!\in_array($page->name(), $redirects)) {
            if ($specialGrant) {
                $warn = $granted('ROLE_SUPERADMIN') && !($page->isAdmin() || $page->isProfiler());

                return RestrictionDecision::open($profiler, $warn ? ['access_restricted.'.$type.'.message', 'warning'] : null);
            }

            return RestrictionDecision::redirect($redirects);
        }
        if ($specialGrant) {
            return RestrictionDecision::open($profiler, ['access_restricted.'.$type.'.on_deny', 'info']);
        }

        return RestrictionDecision::open($profiler);
    }
}
