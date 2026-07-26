<?php

namespace Base\Repository\User;

use App\Entity\User;
use Base\Entity\User\Passkey;
use Base\Database\Repository\ServiceEntityRepository;

use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\Bundle\Repository\CanSaveCredentialRecord;
use Webauthn\Bundle\Repository\CredentialRecordRepositoryInterface;

/**
 * @method Passkey|null find($id, $lockMode = null, $lockVersion = null)
 * @method Passkey|null findOneBy(array $criteria, array $orderBy = null)
 * @method Passkey[]    findAll()
 * @method Passkey[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PasskeyRepository extends ServiceEntityRepository implements CredentialRecordRepositoryInterface, CanSaveCredentialRecord
{
    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
    {
        return $this->findBy(["userHandle" => $publicKeyCredentialUserEntity->id]);
    }

    public function findOneByCredentialId(string $publicKeyCredentialId): ?CredentialRecord
    {
        return $this->findOneBy(["publicKeyCredentialId" => $publicKeyCredentialId]);
    }

    /**
     * The bundle's own AttestationResponseController always calls this with a freshly
     * validated, plain CredentialRecord (never our subclass) - userHandle is the one
     * piece of it that ties back to whoever registered, via WebauthnUserEntityRepository's
     * (string) user id encoding. Resolve that user here and do the real persist.
     */
    public function saveCredentialRecord(CredentialRecord $credentialRecord): void
    {
        $entityManager = $this->getEntityManager();

        if ($credentialRecord instanceof Passkey) {
            $entityManager->persist($credentialRecord);
            $entityManager->flush();
            return;
        }

        $user = $entityManager->getRepository(User::class)->find((int) $credentialRecord->userHandle);
        if (!$user) {
            throw new \RuntimeException('Cannot save passkey: unknown user handle.');
        }

        $entityManager->persist(Passkey::createFromCredentialRecord($credentialRecord, $user));
        $entityManager->flush();
    }
}
