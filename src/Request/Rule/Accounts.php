<?php

namespace Base\Request\Rule;

use Base\Entity\User;
use Base\Request\Decision;
use Base\Request\Page;

/**
 * The account rules of a request, in the order omnibridge/symfony's SecuritySubscriber::onKernelRequest()
 * asked them (plan "omnibase agnostic", step F5e, slice 2), for an omnibase account signed in: the
 * impersonation's reminder, the kick (which ends there), the presence (on a main request), the verification,
 * the approval. Each host's adapter asks request() before the page and response() on its answer.
 */
final class Accounts
{
    public function __construct(
        private readonly Kicked $kicked,
        private readonly Presence $presence,
        private readonly Verification $verification,
        private readonly Approval $approval,
    ) {
    }

    /** @param \Closure(): bool $administrator whether the account is an administrator (the approval asks it) */
    public function request(mixed $user, Page $page, \Closure $administrator, bool $main = true, bool $impersonating = false, ?string $switchParameter = null): Decision
    {
        if (!$user instanceof User) {
            return Decision::continue();
        }

        $decision = Impersonation::decide($user, $impersonating, $switchParameter);
        $kicked = $this->kicked->decide($user, $page);
        if (!$kicked->isContinue()) {
            return $decision->then($kicked);
        }
        if ($main) {
            $this->presence->poke($user);
        }

        return $decision
            ->then($this->verification->decide($user, $page))
            ->then($this->approval->decide($user, $administrator));
    }

    /** On the answer: the presence of an account signed in during the request itself. */
    public function response(mixed $user): void
    {
        $this->presence->poke($user);
    }
}
