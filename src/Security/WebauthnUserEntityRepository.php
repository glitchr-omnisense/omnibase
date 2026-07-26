<?php

namespace Base\Security;

use App\Repository\UserRepository;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\Bundle\Repository\PublicKeyCredentialUserEntityRepositoryInterface;

class WebauthnUserEntityRepository implements PublicKeyCredentialUserEntityRepositoryInterface
{
    protected UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function findOneByUsername(string $username): ?PublicKeyCredentialUserEntity
    {
        $user = $this->userRepository->findOneByUsername($username);
        return $user ? $this->toUserEntity($user) : null;
    }

    public function findOneByUserHandle(string $userHandle): ?PublicKeyCredentialUserEntity
    {
        $user = $this->userRepository->find((int) $userHandle);
        return $user ? $this->toUserEntity($user) : null;
    }

    private function toUserEntity(\App\Entity\User $user): PublicKeyCredentialUserEntity
    {
        return PublicKeyCredentialUserEntity::create(
            $user->getUsername(),
            (string) $user->getId(),
            $user->getFullname() ?: $user->getUsername(),
        );
    }
}
