<?php

namespace Base\Security;

use App\Repository\UserRepository;
use Base\Contract\PasskeyUsers;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * The accounts as passkeys know them (WebauthnBundle's user repository): an
 * account's username is the key's name, its id (as text) the user handle.
 * The bundle's interface is declared through Base\Contract\PasskeyUsers
 * (plan "omniauth", AU1): theirs where the bundle is installed, empty
 * elsewhere, so the class loads without the bundle.
 */
class WebauthnUserEntityRepository implements PasskeyUsers
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
        // name: the login key the authenticator lists (unique); displayName:
        // how the member reads, where the application defines one.
        $displayName = method_exists($user, 'getFullname') ? $user->getFullname() : null;

        return PublicKeyCredentialUserEntity::create(
            $user->getUserIdentifier(),
            (string) $user->getId(),
            $displayName ?: (string) $user,
        );
    }
}
