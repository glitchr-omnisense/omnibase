<?php

namespace Base\Request\Rule;

use Base\Entity\User;
use Base\Request\Decision;
use Omnibridge\Persistence\UnitOfWork;

/**
 * An account not approved yet (plan "omnibase agnostic", step F5e, slice 2; formerly omnibridge/symfony's
 * SecuritySubscriber::onKernelRequest()): approved and written at its first request when it is an
 * administrator's, or when the site approves every sign-up (`base.user.register.autoapprove`); left as it is
 * otherwise, the page going on.
 *
 * The former subscriber also wrote two redirections there (to `security_pendingForApproval`, and from it to
 * the site's index once approved), which could never happen: each was asked only on an account's page
 * ("security_*"), which its own exceptions (`/^(security|user|ux)_/`) always let through.
 */
final class Approval
{
    public function __construct(private readonly ?UnitOfWork $unitOfWork = null, private readonly bool $autoapprove = false)
    {
    }

    /** @param \Closure(): bool $administrator whether the account is an administrator, asked only for an account not approved */
    public function decide(User $user, \Closure $administrator): Decision
    {
        if (!$user->isApproved() && ($administrator() || $this->autoapprove)) {
            $user->approve();
            $this->unitOfWork?->flush();
        }

        return Decision::continue();
    }
}
