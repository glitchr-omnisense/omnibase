<?php

namespace Base\Security\Voter;

use Base\Entity\User;

use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class UserVoter extends Voter
{
    const BALLOT = ["EDIT_ROLES"];
    const EDIT_ROLES = self::BALLOT[0];

    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        // Voter only support "User" objects and one specific ballot type..
        return ($subject instanceof User) && in_array($attribute, self::BALLOT);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        //
        // Check if current user is authenticated
        $user = $token->getUser();
        if (!$user instanceof User) return false;

        //
        // Select the proper ballot
        switch($attribute) {

            case self::EDIT_ROLES:
                return ($this->security->isGranted("ROLE_EDITOR")) ? self::ACCESS_GRANTED : self::ACCESS_DENIED;
        }

        return self::ACCESS_ABSTAIN;
    }
}