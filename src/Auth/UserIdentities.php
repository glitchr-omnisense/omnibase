<?php

namespace Base\Auth;

use Base\Entity\User\Identity;
use Base\Repository\User\IdentityRepository;
use Omniauth\Contract\Identities;

/**
 * omniauth's identities at providers: the userIdentity table
 * (Base\Entity\User\Identity), by Marco's decision QA3 - an account is found
 * by the provider's subject, never by user.identityProvider (which keeps its
 * own meaning: where the account came from) nor by the address alone. The
 * table's two unique keys refuse what omniauth/oauth refuses before them: a
 * subject linked to two accounts, two identities of one provider on one
 * account.
 */
class UserIdentities extends UserStore implements Identities
{
    public function owner(string $provider, string $subject): ?object
    {
        return $this->repository()->findOneBySubject($provider, $subject)?->getUser();
    }

    public function link(object $account, string $provider, string $subject, ?string $email = null, bool $emailVerified = false): void
    {
        $user = $this->user($account);
        $identity = $this->repository()->findOneBySubject($provider, $subject);
        if (null !== $identity) {
            if ($identity->getUser()?->getId() !== $user->getId()) {
                throw new \LogicException(\sprintf('That identity of "%s" is linked to another account.', $provider));
            }

            return;
        }
        $this->entityManager->persist(new Identity($user, $provider, $subject, $email, $emailVerified));
        $this->save();
    }

    public function unlink(object $account, string $provider): void
    {
        $removed = false;
        foreach ($this->repository()->findOfUser($this->user($account)) as $identity) {
            if ($identity->getProvider() === $provider) {
                $this->entityManager->remove($identity);
                $removed = true;
            }
        }
        if ($removed) {
            $this->save();
        }
    }

    public function used(string $provider, string $subject, \DateTimeImmutable $at): void
    {
        $identity = $this->repository()->findOneBySubject($provider, $subject);
        if (null !== $identity) {
            $identity->markUsed($at);
            $this->save();
        }
    }

    public function providers(object $account): array
    {
        return array_values(array_unique(array_map(static fn (Identity $identity) => $identity->getProvider(), $this->repository()->findOfUser($this->user($account)))));
    }

    /** The account's identities, for its settings page. @return list<Identity> */
    public function of(object $account): array
    {
        return $this->repository()->findOfUser($this->user($account));
    }

    private function repository(): IdentityRepository
    {
        return $this->entityManager->getRepository(Identity::class);
    }
}
