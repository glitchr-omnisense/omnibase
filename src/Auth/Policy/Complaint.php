<?php

namespace Base\Auth\Policy;

use Base\Entity\User;
use Base\Entity\User\Complaint as ComplaintEntity;
use Omnibridge\Contract\Roles;

/**
 * Who reads and answers a complaint (plan "omnibase agnostic", step F5e, slice 1; Base\Entity\User\Complaint):
 * COMPLAINT_READ / COMPLAINT_HANDLE, the complaint as subject, or none (may this user read complaints at all).
 *
 * - to read complaints at all: the reader role (ROLE_MODERATOR by default), or the permission tag
 *   COMPLAINT.READ / COMPLAINT.HANDLE given to the user or a group (the host's own decision on the tag);
 * - a complaint about somebody of the staff is only for those who OUTRANK them: whose reachable roles hold
 *   all of theirs and more (the role hierarchy, the Roles contract) - moderators do not judge a moderator;
 * - nobody reads a complaint about themselves, except the top role (ROLE_SUPERADMIN by default), which reads
 *   them all.
 */
class Complaint
{
    public const READ = 'COMPLAINT_READ';
    public const HANDLE = 'COMPLAINT_HANDLE';

    public function __construct(
        private readonly Roles $roles,
        private readonly string $readerRole = 'ROLE_MODERATOR',
        private readonly string $topRole = 'ROLE_SUPERADMIN',
    ) {
    }

    public function supports(string $attribute, mixed $subject): bool
    {
        return (self::READ === $attribute || self::HANDLE === $attribute) && (null === $subject || $subject instanceof ComplaintEntity);
    }

    /** @param \Closure(string): bool $hasTag the host's decision on a permission tag, for the same account */
    public function decide(string $attribute, mixed $subject, mixed $user, \Closure $hasTag): bool
    {
        if (!$user instanceof User) {
            return false;
        }
        $reach = $this->reach($user->getRoles());
        if (\in_array($this->topRole, $reach, true)) {
            return true;
        }
        $tag = self::READ === $attribute ? 'COMPLAINT.READ' : 'COMPLAINT.HANDLE';
        if (!\in_array($this->readerRole, $reach, true) && !$hasTag($tag)) {
            return false;
        }
        if (!$subject instanceof ComplaintEntity) {
            return true;
        }

        $target = $subject->getTarget();
        if ($target === $user || ($target && $target->getId() === $user->getId())
            || (null !== $subject->getTargetName() && 0 === strcasecmp($subject->getTargetName(), (string) $user))) {
            return false;
        }
        if (!$target instanceof User) {
            return true;
        }
        // Outranking: every role they reach, I reach too - and more.
        $theirs = $this->reach($target->getRoles());

        return [] === array_diff($theirs, $reach) && [] !== array_diff($reach, $theirs);
    }

    /** @return list<string> */
    private function reach(array $roles): array
    {
        return array_values(array_unique($this->roles->reachable($roles)));
    }
}
